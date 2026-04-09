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

            $chat->update([
                'last_message_id' => $message->id,
            ]);

            $message->load(['attachments', 'user', 'chat' => ['participants']]);

            broadcast(new MessageCreated($message))->toOthers();
            DB::commit();

        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return successResponse(
            MessageResource::make($message),
            'Message sent successfully'
        );
    }

    public function downloadAttachment(Attachment $attachment)
    {
        try {
            $user = Auth::user();

            $message = $attachment->message;
            $chat = $message->chat;

            if (!$user->chats()->where('chats.id', $chat->id)->exists()) {
                abort(403, 'You do not have access to this attachment.');
            }

            return successResponse([
                Storage::temporaryUrl(
                    $attachment->path,
                    now()->addMinutes(30)
                ),
            ]);

        } catch (Throwable $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }
}
