<?php

namespace App\Enums;

enum Role: string
{
    case Candidate = 'candidate';
    case Employer = 'employer';
    case Admin = 'admin';

    /**
     * Roles a user may pick for themselves during onboarding.
     *
     * @return list<string>
     */
    public static function selectable(): array
    {
        return [self::Candidate->value, self::Employer->value];
    }
}
