<?php

namespace App\Http\Resources;

use App\Enums\ChatTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ChatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,

            'label' => $this->resolveLabel(),
            'avatar' => $this->resolveAvatar(),

            'participants' => UserResource::collection(
                $this->whenLoaded('participants', fn () => $this->filteredParticipants())
            ),

            'messages' => MessageResource::collection($this->whenLoaded('messages')),

            'last_message' => MessageResource::make(
                $this->whenLoaded('lastMessage')
            ),

            'new_messages' => $this->whenCounted('unread_messages_count'),

            'created_at' => $this->created_at?->diffForHumans(),
        ];
    }

    /**
     * Filter participants based on chat type
     */
    protected function filteredParticipants()
    {
        if (! $this->relationLoaded('participants')) {
            return collect();
        }

        // if the chat is a group, return all participants
        if ($this->type === ChatTypeEnum::GROUP) {
            return $this->participants;
        }

        // if the chat is a peer, return the other participant
        return $this->participants
            ->where('id', '!=', auth()->id())
            ->values();
    }

    /**
     * Resolve chat label safely
     */
    protected function resolveLabel(): ?string
    {
        if ($this->type === ChatTypeEnum::GROUP) {
            return $this->label;
        }

        if (! $this->relationLoaded('participants')) {
            return null;
        }

        return optional(
            $this->participants->firstWhere('id', '!=', auth()->id())
        )->name;
    }

    /**
     * Resolve chat avatar safely
     */
    protected function resolveAvatar(): ?string
    {
        if ($this->type === ChatTypeEnum::GROUP) {
            return $this->avatar
                ? Storage::disk('public')->url($this->avatar)
                : null;
        }

        if (! $this->relationLoaded('participants')) {
            return null;
        }

        return optional(
            $this->participants->firstWhere('id', '!=', auth()->id())
        )->avatar;
    }
}
