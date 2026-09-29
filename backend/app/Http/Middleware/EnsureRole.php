<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:admin') or ->middleware('role:admin,employer')
 * Coarse route-level gate; fine-grained checks live in Policies.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->needsOnboarding()) {
            return response()->json([
                'message' => 'Please choose whether you are a candidate or an employer first.',
                'code' => 'onboarding_required',
            ], 403);
        }

        $allowed = array_map(fn (string $role) => Role::from($role), $roles);

        if (! $user->hasRole(...$allowed)) {
            return response()->json(['message' => 'You do not have access to this area.'], 403);
        }

        return $next($request);
    }
}
