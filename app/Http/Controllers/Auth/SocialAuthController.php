<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    protected array $providers = ['google', 'github'];

    public function redirect(string $provider)
    {
        $this->validateProvider($provider);

        // $url = Socialite::driver($provider)->stateless()->redirect()->getTargetUrl();
        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        $this->validateProvider($provider);

        try {
            // $socialUser = Socialite::driver($provider)->stateless()->user();
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return errorResponse('Authentication failed: ' . $e->getMessage(), 401);
        }

        if (!$socialUser->getEmail()) {
            return errorResponse('No email address found from ' . $provider . '. Please ensure your email is public.', 422);
        }

        // Check if this social account already exists
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            $user = $socialAccount->user;

            $socialAccount->update([
                'provider_token' => $socialUser->token,
                'provider_refresh_token' => $socialUser->refreshToken,
            ]);
        } else {
            // Check if a user with the same email exists
            $user = User::where('email', $socialUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'username' => $this->generateUniqueUsername($socialUser),
                    'email' => $socialUser->getEmail(),
                    'password' => null,
                    'email_verified_at' => now(),
                ]);

                event(new Registered($user));
            }

            // Link the social account
            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'provider_token' => $socialUser->token,
                'provider_refresh_token' => $socialUser->refreshToken,
            ]);
        }

        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->away(config('app.frontend_url'));
    }

    private function validateProvider(string $provider): void
    {
        if (!in_array($provider, $this->providers)) {
            abort(422, 'Unsupported provider.');
        }
    }

    private function generateUniqueUsername($socialUser): string
    {
        $base = $socialUser->getNickname()
            ?? Str::slug($socialUser->getName(), '_')
            ?? Str::before($socialUser->getEmail(), '@');

        $username = Str::limit($base, 250, '');

        while (User::where('username', $username)->exists()) {
            $username = $base . '_' . rand(100, 9999);
        }

        return $username;
    }
}
