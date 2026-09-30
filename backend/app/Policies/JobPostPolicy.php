<?php

namespace App\Policies;

use App\Models\JobPost;
use App\Models\User;

class JobPostPolicy
{
    /**
     * An employer may only view/manage a job that belongs to their own
     * company profile.
     */
    public function view(User $user, JobPost $jobPost): bool
    {
        return $this->owns($user, $jobPost);
    }

    public function update(User $user, JobPost $jobPost): bool
    {
        return $this->owns($user, $jobPost);
    }

    public function close(User $user, JobPost $jobPost): bool
    {
        return $this->owns($user, $jobPost);
    }

    /**
     * Publishing additionally requires an approved employer account. The
     * clearer "pending approval" message is surfaced by the controller; this
     * gate only settles ownership + role so a non-owner still 403s here.
     */
    public function publish(User $user, JobPost $jobPost): bool
    {
        return $this->owns($user, $jobPost);
    }

    private function owns(User $user, JobPost $jobPost): bool
    {
        return $user->isEmployer()
            && $jobPost->employerProfile !== null
            && $jobPost->employerProfile->user_id === $user->id;
    }
}
