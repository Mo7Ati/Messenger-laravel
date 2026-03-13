<?php

namespace App\Http\Resources;

use App\Enums\ChatTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'label' => $this->getLabel(),
            'participants' => UserResource::collection($this->whenLoaded('participants')),
            'last_message' => $this->whenLoaded('lastMessage', fn() => $this->last_message_id
                ? MessageResource::make($this->lastMessage)
                : null),
            'new_messages' => $this->whenCounted('recipients', $this->recipients_count),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }

    public function getLabel(): string
    {
        return match ($this->type) {
            ChatTypeEnum::PEER => $this->participants->first()->username,
            ChatTypeEnum::GROUP => $this->label,
            default => 'No label',
        };
    }
}
