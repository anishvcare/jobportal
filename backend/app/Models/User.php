<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['google_id', 'name', 'email', 'avatar_url', 'last_login_at'])]
#[Hidden(['google_id', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'role' => null,
    ];

    /**
     * Google-only sign-in: users have no password.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function hasRole(Role ...$roles): bool
    {
        return $this->role !== null && in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isCandidate(): bool
    {
        return $this->role === Role::Candidate;
    }

    public function isEmployer(): bool
    {
        return $this->role === Role::Employer;
    }

    public function needsOnboarding(): bool
    {
        return $this->role === null;
    }

    /**
     * Whether this email is configured as an admin via ADMIN_EMAILS.
     */
    public function isConfiguredAdmin(): bool
    {
        return in_array(strtolower($this->email), config('nexus.admin_emails'), true);
    }

    /**
     * Keep the admin role in sync with ADMIN_EMAILS.
     * Removing an email from the list demotes that user back to onboarding.
     */
    public function syncAdminRole(): void
    {
        if ($this->isConfiguredAdmin()) {
            $this->role = Role::Admin;
        } elseif ($this->role === Role::Admin) {
            $this->role = null;
        }
    }
}
