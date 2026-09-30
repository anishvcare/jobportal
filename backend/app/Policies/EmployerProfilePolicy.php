<?php

namespace App\Policies;

use App\Models\EmployerProfile;
use App\Models\User;

class EmployerProfilePolicy
{
    /**
     * An employer may only act on their own company profile.
     */
    public function view(User $user, EmployerProfile $profile): bool
    {
        return $this->owns($user, $profile);
    }

    public function update(User $user, EmployerProfile $profile): bool
    {
        return $this->owns($user, $profile);
    }

    private function owns(User $user, EmployerProfile $profile): bool
    {
        return $user->isEmployer() && $profile->user_id === $user->id;
    }
}
