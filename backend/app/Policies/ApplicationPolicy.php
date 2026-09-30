<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * An employer may only act on an application to one of their OWN jobs.
     */
    public function view(User $user, Application $application): bool
    {
        return $this->ownsJob($user, $application);
    }

    public function updateStatus(User $user, Application $application): bool
    {
        return $this->ownsJob($user, $application);
    }

    public function downloadResume(User $user, Application $application): bool
    {
        return $this->ownsJob($user, $application);
    }

    private function ownsJob(User $user, Application $application): bool
    {
        return $user->isEmployer()
            && $application->jobPost !== null
            && $application->jobPost->employerProfile !== null
            && $application->jobPost->employerProfile->user_id === $user->id;
    }
}
