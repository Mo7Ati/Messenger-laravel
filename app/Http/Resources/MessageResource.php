<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'chat_id' => $this->chat_id,
            'body' => $this->body,
            'type' => $this->type,
            'is_mine' => $this->user_id == Auth::id(),
            'user_id' => $this->user_id,
            'user' => UserResource::make($this->whenLoaded('user')),
            'attachments' => MessageAttachmentResource::collection($this->whenLoaded('attachments')),
            'chat' => ChatResource::make($this->whenLoaded('chat')),
            'created_at' => $this->created_at?->format('g:i A'),
            'is_read_by_all' => $this->whenLoaded('recipients', function () {
                return $this->recipients->every(function ($recipient) {
                    return (bool) $recipient->read_at;
                });
            }),
        ];
    }
}
