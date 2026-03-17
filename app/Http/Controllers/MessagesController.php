<?php

namespace App\Http\Controllers;

use App\Events\MessageCreated;
use App\Http\Resources\MessageResource;
use App\Models\Attachment;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Recipient;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Throwable;

class MessagesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($id)
    {
        $user = Auth::user();
        $messages = [];

        $chat = $user->chats()->find($id);

        $messages = $chat->messages()
            ->with(['user', 'attachments'])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereRaw('id in (
                    select message_id from recipients
                    where messages.id = recipients.message_id and
                    recipients.user_id = ? and
                    recipients.deleted_at is null
                )', [$user->id]);
            })->get();

        // $messages = DB::select('
        //       SELECT * FROM messages
        //       inner join recipients on recipients.message_id = messages.id
        //       where messages.chat_id = ?

        //  ', [$chat->id]);

        // $chat->messages()->with('user:id,name')->get();
        $participants = $chat->participants()->where('user_id', '<>', $user->id)->get();

        return response()->json([
            'messages' => $messages,
            'participants' => $participants,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'message' => ['nullable', 'string', 'max:65535'],
            'chat_id' => ['required_without:user_id', 'integer', 'exists:chats,id'],
            'user_id' => ['required_without:chat_id', 'integer', 'exists:users,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'mp3', 'mp4', 'wav'])
                    ->max(5 * 1024),
            ],
        ]);

        $hasAttachments = $request->hasFile('attachments') && count($request->file('attachments')) > 0;
        $body = trim((string) $request->post('message', ''));
        $chat_id = $request->post('chat_id');
        $user_id = $request->post('user_id');

        DB::beginTransaction();
        try {
            if ($chat_id) {
                $chat = $user->chats()->findOrFail($chat_id);
            } else {
                $chat = Chat::where('type', 'peer')
                    ->whereHas(
                        'participants',
                        function (Builder $builder) use ($user, $user_id) {
                            $builder->join('participants as participants2', 'participants2.chat_id', '=', 'participants.chat_id')
                                ->where('participants.user_id', $user->id)
                                ->where('participants2.user_id', $user_id);
                        }
                    )->first();

                if (!$chat) {
                    $chat = Chat::create([
                        'user_id' => $user->id,
                        'type' => 'peer',
                    ]);
                    $chat->participants()->attach([$user_id, $user->id]);

                }
            }

            $messageType = $hasAttachments ? 'attachment' : 'text';
            $message = $chat->messages()->create([
                'user_id' => $user->id,
                'body' => $body,
                'type' => $messageType,
            ]);

            if ($hasAttachments) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('attachments');
                    $message->attachments()->create([
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }
            }

            DB::statement('
                INSERT INTO recipients (user_id, message_id)
                SELECT user_id, ? FROM participants
                WHERE chat_id = ?
                and participants.user_id <> ?
            ', [$message->id, $chat->id, $user->id]);

            // $participants = $chat->participants()->where('user_id', '<>', $user->id)->get();
            // foreach ($participants as $participant) {
            //     $message->recipients()->attach([
            //         'user_id' => $participant->id,
            //     ]);
            // }

            $chat->update([
                'last_message_id' => $message->id,
            ]);

            broadcast(new MessageCreated($message))->toOthers();
            DB::commit();

        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $message->load(['attachments', 'user']);

        return successResponse(
            MessageResource::make($message),
            'Message sent successfully'
        );
    }

    public function downloadAttachment(Attachment $attachment)
    {
        $user = Auth::user();
        $message = $attachment->message;
        $chat = $message->chat;

        if (!$user->chats()->where('chats.id', $chat->id)->exists()) {
            abort(403, 'You do not have access to this attachment.');
        }

        $path = $attachment->path;
        if (!Storage::exists($path)) {
            abort(404, 'File not found.');
        }

        return Storage::download(
            $path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
            ]
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $message = Message::findOrFail($id);
        $message->update([
            'body' => $request->post('message'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Recipient::where([
            'user_id' => Auth::id(),
            'message_id' => $id,
        ])->delete();

        return [
            'Message Deleted',
        ];
    }

}
