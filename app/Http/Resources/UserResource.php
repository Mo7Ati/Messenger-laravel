<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'avatar_url' => $this->avatar_url,
            'bio' => $this->bio,
            'phone' => $this->phone,
        ];
    }
    public function serializeForContacts(): array
    {
        $currentUser = Auth::user();

        return [
            ...$this->toArray(request()),
            'contact_status' => $currentUser->contactStatus($this->id),
        ];
    }
}
