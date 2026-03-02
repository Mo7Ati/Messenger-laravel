<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // 'avatar_url' => $this->avatar_url,
            'last_active_at' => $this->last_active_at,
            'created_at' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at,
        ];
    }
}
