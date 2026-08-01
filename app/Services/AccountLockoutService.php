<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

/**
 * Backs the login pipeline's use of users.failed_login_attempts/locked_until
 * against the seeded security.max_failed_login_attempts/
 * security.account_lockout_minutes settings (Part 17.18 of the SRS).
 */
class AccountLockoutService
{
    public function maxAttempts(): int
    {
        return (int) ($this->setting('max_failed_login_attempts') ?? 5);
    }

    public function lockoutMinutes(): int
    {
        return (int) ($this->setting('account_lockout_minutes') ?? 30);
    }

    public function isLocked(User $user): bool
    {
        return $user->locked_until !== null && $user->locked_until->isFuture();
    }

    public function recordFailure(User $user): void
    {
        $user->failed_login_attempts++;

        if ($user->failed_login_attempts >= $this->maxAttempts()) {
            $user->locked_until = now()->addMinutes($this->lockoutMinutes());
        }

        $user->save();
    }

    public function recordSuccess(User $user): void
    {
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->last_login_at = now();
        $user->save();
    }

    private function setting(string $key): ?string
    {
        return Setting::where('setting_group', 'security')
            ->where('setting_key', $key)
            ->value('setting_value');
    }
}
