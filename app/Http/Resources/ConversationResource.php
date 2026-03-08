<?php

namespace App\Http\Resources;

use App\Enums\ConversationTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
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
            'participants' => ParticipantResource::collection($this->whenLoaded('participants')),
            'last_message' => MessageResource::make($this->whenLoaded('lastMessage')),
            'new_messages' => $this->whenCounted('recipients', $this->recipients_count),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }

    public function getLabel(): string
    {
        return match ($this->type) {
            ConversationTypeEnum::PEER => $this->participants->first()->name,
            ConversationTypeEnum::GROUP => $this->label,
            default => 'No label',
        };
    }
}
