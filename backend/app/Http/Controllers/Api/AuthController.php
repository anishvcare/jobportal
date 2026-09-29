<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ExchangeCodeRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\LoginCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function exchange(ExchangeCodeRequest $request, LoginCodeService $codes): UserResource
    {
        $user = $codes->redeem($request->string('code')->toString());

        if ($user === null) {
            throw ValidationException::withMessages([
                'code' => 'This sign-in link has expired. Please sign in again.',
            ]);
        }

        // Re-check admin status at session creation (ADMIN_EMAILS may have changed).
        $user->syncAdminRole();
        $user->save();

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        return new UserResource($user);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
