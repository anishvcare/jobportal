<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * A candidate may only act on documents attached to their own profile.
     */
    public function view(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    private function owns(User $user, Document $document): bool
    {
        if (! $user->isCandidate()) {
            return false;
        }

        $profileId = $user->candidateProfile()->value('id');

        return $profileId !== null && $document->candidate_profile_id === $profileId;
    }
}
