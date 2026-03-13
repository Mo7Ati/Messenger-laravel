<?php

namespace App\Events;

use App\Http\Resources\ChatResource;
use App\Models\Chat;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
class GroupCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Chat $chat;

    public function __construct(Chat $chat)
    {
        $this->chat = $chat;
    }

    public function broadcastOn(): array
    {
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
        $chat = $this->chat->load([
            'participants' => fn($query) => $query->where('users.id', '<>', $this->chat->user_id),
        ]);

        return [
            'group' => ChatResource::make($chat),
        ];
    }
}
