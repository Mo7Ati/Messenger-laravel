<?php

namespace App\Http\Resources;


use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;


class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at->format('H:i'),
            'is_mine' => $this->user_id == Auth::id(),
            'is_read_by_all' => $this->whenLoaded('recipients', function ($recipients) {
                return $recipients->every(function ($recipient) {
                    return $recipient->read_at ? true : false;
                });
            }),
            'chat_id' => $this->conversation_id,
        ];
    }
}
