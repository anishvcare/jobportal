<?php

namespace App\Policies;

use App\Models\CandidateProfile;
use App\Models\User;

class CandidateProfilePolicy
{
    /**
     * A candidate may only act on their own profile.
     */
    public function view(User $user, CandidateProfile $profile): bool
    {
        return $this->owns($user, $profile);
    }

    public function update(User $user, CandidateProfile $profile): bool
    {
        return $this->owns($user, $profile);
    }

    public function delete(User $user, CandidateProfile $profile): bool
    {
        return $this->owns($user, $profile);
    }

    private function owns(User $user, CandidateProfile $profile): bool
    {
        return $user->isCandidate() && $profile->user_id === $user->id;
    }
}
