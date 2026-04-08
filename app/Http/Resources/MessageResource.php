<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class MessageResource extends JsonResource
{
    public function toArray(Request $request, ?bool $isMine = null): array
    {
        $isMine = $isMine ?? ($this->user_id == Auth::id());

        return [
            'id' => $this->id,
            'chat_id' => $this->chat_id,
            'body' => $this->body,
            'type' => $this->type,
            'is_mine' => $isMine,
            'user_id' => $this->user_id,
            'user' => UserResource::make($this->whenLoaded('user')),
            'attachments' => MessageAttachmentResource::collection($this->whenLoaded('attachments')),
            'chat' => ChatResource::make($this->whenLoaded('chat')),
            'created_at' => $this->created_at,
            'status' => $this->when(
                $isMine,
                fn() => $this->whenLoaded('recipients', function ($recipients) {
                    $readers = $recipients->filter(
                        fn($r) => (bool) $r->pivot->read_at
                    );

                    return [
                        'is_read_by_all' => $readers->count() === $recipients->count(),
                        'readers' => UserResource::collection($readers),
                    ];
                })
            ),
        ];
    }
}
