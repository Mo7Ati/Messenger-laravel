<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class MessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $participantIds = DB::table('participants')
            ->where('chat_id', $this->message->chat_id)
            ->pluck('user_id');

        return $participantIds
            ->map(fn(int $userId) => new PrivateChannel('messenger.user.' . $userId))
            ->all();
    }


    public function broadcastWith(): array
    {
        return [
            'message' => MessageResource::make($this->message)->toArray(request(), isMine: false),
        ];
    }
}
