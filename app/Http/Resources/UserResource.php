<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUser = Auth::user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'bio' => $this->bio,
            'phone' => $this->phone,
            'contact_status' => $this->when(
                $currentUser && $this->id != $currentUser->id,
                fn() => $currentUser->contactStatus($this->id)
            ),
            'role' => $this->whenPivotLoaded('participants', function () {
                return $this->pivot?->role;
            }),
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
