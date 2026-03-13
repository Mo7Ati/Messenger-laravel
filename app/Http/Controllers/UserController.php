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
     * Search users by username, name, or email.
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
                $q->where('username', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(20)
            ->get(['id', 'username', 'avatar', 'bio']);

        // $results = $users->map(function (User $user) use ($currentUser) {
        //     $contactStatus = 'none';
        //     if ($currentUser->isContactWith($user->id)) {
        //         $contactStatus = 'contacts';
        //     } elseif ($currentUser->hasSentRequestTo($user->id)) {
        //         $contactStatus = 'request_sent';
        //     } elseif ($currentUser->hasPendingRequestFrom($user->id)) {
        //         $contactStatus = 'request_received';
        //     }

        //     return [
        //         'id' => $user->id,
        //         'name' => $user->name,
        //         'username' => $user->username,
        //         'avatar' => $user->avatar,
        //         'bio' => $user->bio,
        //         'contact_status' => $contactStatus,
        //     ];
        // });

        return successResponse(
            UserResource::collection($users),
            'Search results',
            200
        );
    }
}
