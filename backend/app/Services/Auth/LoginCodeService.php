<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Single-use, short-lived codes that bridge the OAuth callback (on the API
 * domain) to a session created by the frontend's own request.
 * Only a SHA-256 hash of the code is stored.
 */
class LoginCodeService
{
    public function issue(User $user): string
    {
        $code = Str::random(64);

        Cache::put($this->key($code), $user->getKey(), config('nexus.login_code_ttl'));

        return $code;
    }

    /**
     * Returns the user and invalidates the code, or null if invalid/expired/used.
     */
    public function redeem(string $code): ?User
    {
        $key = $this->key($code);

        $lock = Cache::lock($key.':lock', 5);
        if (! $lock->get()) {
            return null;
        }

        try {
            $userId = Cache::pull($key);
        } finally {
            $lock->release();
        }

        return $userId ? User::find($userId) : null;
    }

    private function key(string $code): string
    {
        return 'login-code:'.hash('sha256', $code);
    }
}
