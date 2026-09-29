<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChooseRoleRequest;
use App\Http\Resources\UserResource;

class OnboardingController extends Controller
{
    public function chooseRole(ChooseRoleRequest $request): UserResource
    {
        $user = $request->user();
        $user->role = Role::from($request->string('role')->toString());
        $user->save();

        return new UserResource($user);
    }
}
