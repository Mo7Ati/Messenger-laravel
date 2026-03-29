<?php

namespace App\Events;

use App\Http\Resources\ChatResource;
use App\Models\Chat;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AddedToGroup implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Chat $chat, public ?int $newUserId = null)
    {
    }

    public function broadcastOn(): array
    {
        if ($this->newUserId) {
            return [new PrivateChannel('messenger.user.' . $this->newUserId)];
        }

        return $this->chat
            ->participants()
            ->where('users.id', '<>', $this->chat->user_id)
            ->get()
            ->pluck('id')
            ->map(fn(int $userId) => new PrivateChannel('messenger.user.' . $userId))
            ->all();
    }

    public function broadcastWith(): array
    {
        return [
            'group' => ChatResource::make($this->chat),
        ];
    }
}
