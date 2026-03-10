<?php

namespace App\Events;

use App\Http\Resources\ChatResource;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class MessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    // public $chat;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message)
    {
        $this->message = MessageResource::make($message->load(['user', 'attachments']));
        // $this->chat = ChatResource::make($chat->load('lastMessage'));
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $participantIds = DB::table('participants')
            ->where('chat_id', $this->message->chat_id)
            ->pluck('user_id');

        return $participantIds
            ->map(fn (int $userId) => new PrivateChannel('messenger.user.'.$userId))
            ->all();
    }
}
