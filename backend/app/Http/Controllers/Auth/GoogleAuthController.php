<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\LoginCodeService;
use App\Support\SafeRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Google OAuth for the SPA.
 *
 * The callback does NOT rely on the session cookie reaching the frontend.
 * It hands the frontend a single-use code, and the frontend calls
 * POST /api/auth/exchange to create the session in its own cookie jar.
 * This keeps login working inside installed PWAs (notably iOS standalone mode).
 */
class GoogleAuthController extends Controller
{
    public function __construct(private readonly LoginCodeService $codes) {}

    public function redirect(Request $request): SymfonyRedirect
    {
        $request->session()->put('auth.redirect', SafeRedirect::path($request->query('redirect')));

        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $redirect = $request->session()->pull('auth.redirect');

        if ($request->filled('error')) {
            return $this->toFrontend('/auth/login', ['error' => 'cancelled']);
        }

        try {
            /** @var GoogleUser $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->toFrontend('/auth/login', ['error' => 'expired']);
        } catch (Throwable $e) {
            Log::warning('Google OAuth failed', ['message' => $e->getMessage()]);

            return $this->toFrontend('/auth/login', ['error' => 'failed']);
        }

        $email = strtolower((string) $googleUser->getEmail());
        $verified = (bool) ($googleUser->user['email_verified'] ?? false);

        if ($email === '' || ! $verified) {
            return $this->toFrontend('/auth/login', ['error' => 'unverified']);
        }

        $user = $this->upsertUser($googleUser, $email);

        $params = ['code' => $this->codes->issue($user)];
        if ($redirect !== null) {
            $params['redirect'] = $redirect;
        }

        return $this->toFrontend('/auth/callback', $params);
    }

    private function upsertUser(GoogleUser $googleUser, string $email): User
    {
        return DB::transaction(function () use ($googleUser, $email) {
            $user = User::where('google_id', $googleUser->getId())->lockForUpdate()->first()
                ?? User::where('email', $email)->lockForUpdate()->first()
                ?? new User;

            $user->fill([
                'google_id' => $googleUser->getId(),
                'email' => $email,
                'name' => $googleUser->getName() ?: $email,
                'avatar_url' => $googleUser->getAvatar(),
                'last_login_at' => now(),
            ]);
            $user->syncAdminRole();
            $user->save();

            return $user;
        });
    }

    /**
     * @param  array<string, string>  $query
     */
    private function toFrontend(string $path, array $query = []): RedirectResponse
    {
        $url = config('nexus.frontend_url').$path;
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return redirect()->away($url);
    }
}
