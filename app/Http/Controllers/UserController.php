<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Search users by name, email.
     * Only discoverable users, excludes current user.
     * Adds contact_status for each result.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->query(key: 'query', default: '');

        $currentUser = Auth::user();
        if (strlen($query) < 2) {
            return successResponse([], 'Search requires at least 2 characters', 200);
        }

        $users = User::query()
            ->where('is_discoverable', true)
            ->where('id', '!=', $currentUser->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(20)
            ->get(['id', 'name', 'avatar', 'bio']);

        return successResponse(
            $users->map(fn(User $user) => UserResource::make($user)->serializeForContacts())->toArray(),
            'Search results',
            200
        );
    }
}
