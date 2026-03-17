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
        $data = $request->only(['username', 'bio', 'email']);

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

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = Auth::user();
        $user->update([
            'password' => $request->password,
        ]);

        return successResponse(null, 'Password updated successfully.');
    }
}
