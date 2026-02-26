<?php

namespace App\Http\Controllers;

use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

        $conversation = $user->conversations()->find($id);

        $messages = $conversation->messages()
            ->with('user')
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
        //       where messages.conversation_id = ?

        //  ', [$conversation->id]);

        //$conversation->messages()->with('user:id,name')->get();
        $participants = $conversation->participants()->where('user_id', '<>', $user->id)->get();

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
            'message' => ['required', 'string'],
            'conversation_id' => ['required_without:user_id', 'integer', 'exists:conversations,id'],
            'user_id' => ['required_without:conversation_id', 'integer', 'exists:users,id'],
        ]);

        $conversation_id = $request->post('conversation_id');
        $user_id = $request->post('user_id');

        DB::beginTransaction();
        try {
            if ($conversation_id) {
                $conversation = $user->conversations()->findOrFail($conversation_id);
            } else {
                $conversation = Conversation::where('type', 'peer')
                    ->whereHas(
                        'participants',
                        function (Builder $builder) use ($user, $user_id) {
                            $builder->join('participants as participants2', 'participants2.conversation_id', '=', 'participants.conversation_id')
                                ->where('participants.user_id', $user->id)
                                ->where('participants2.user_id', $user_id);
                        }
                    )->first();

                if (!$conversation) {
                    $conversation = Conversation::create([
                        'user_id' => $user->id,
                        'type' => 'peer',
                    ]);
                    $conversation->participants()->attach([$user_id, $user->id]);
                }
            }

            $message = $conversation->messages()->create([
                'user_id' => $user->id,
                'body' => $request->post('message'),
            ]);

            DB::statement('
                INSERT INTO recipients (user_id, message_id)
                SELECT user_id, ? FROM participants
                WHERE conversation_id = ?
                and participants.user_id <> ?
            ', [$message->id, $conversation->id, $user->id]);


            // $participants = $conversation->participants()->where('user_id', '<>', $user->id)->get();
            // foreach ($participants as $participant) {
            //     $message->recipients()->attach([
            //         'user_id' => $participant->id,
            //     ]);
            // }

            $conversation->update([
                'last_message_id' => $message->id,
            ]);
            DB::commit();

            if ($conversation->type == 'peer') {
                $other_user = $conversation
                    ->participants()
                    ->where('user_id', '<>', $message->user_id)->first();
                broadcast(new MessageCreated($message, $other_user));

            } else {
                $participants = $conversation
                    ->participants()
                    ->where('user_id', '<>', $message->user_id)->get();

                foreach ($participants as $participant) {
                    broadcast(new MessageCreated($message, $participant));
                }
            }


        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $conversation->load([
            'participants' => function ($builder) use ($user) {
                return $builder->where('user_id', '<>', $user->id);
            },
            'lastMessage',
        ]);

        return [
            "message" => $message->load('user'),
            "chat" => $conversation
        ];
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
            'body' => $request->post('message')
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

    public function markAsRead(string $id)
    {
        $user = Auth::user();
        DB::beginTransaction();
        try {
            DB::statement('
                UPDATE recipients
                inner join messages on messages.id = recipients.message_id
                SET recipients.read_at = ?
                where recipients.user_id = ?
                      and messages.conversation_id = ?
                      and recipients.read_at is null
            ', [now(), $user->id, $id]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        return [
            'message' => 'All Messages Read'
        ];
    }
}
