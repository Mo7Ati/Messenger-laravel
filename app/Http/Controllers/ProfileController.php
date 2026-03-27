<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = Auth::user();
        $data = $request->only(['name', 'bio', 'email', 'phone']);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);
        $user->refresh();

        return successResponse(UserResource::make($user), 'Profile updated successfully');
    }

    public function deleteAvatar()
    {
        $user = Auth::user();

        if (!$user->avatar) {
            return errorResponse('No avatar to delete.', 404);
        }

        Storage::disk('public')->delete($user->avatar);
        $user->update(['avatar' => null]);

        return successResponse(UserResource::make($user->refresh()), 'Avatar deleted successfully.');
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = Auth::user();
        $user->update([
            'password' => $request->password,
        ]);

        return successResponse(null, 'Password updated successfully.');
    }
}
