<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Decides whether an email and password may sign in, and keeps the failed-attempt counter.
 *
 * Five wrong passwords in a row lock the account for 15 minutes. Every refusal looks the same to the
 * caller (null), whether the email is unknown, the password is wrong, the account is locked or it is
 * deactivated, so the sign-in form cannot be used to find out which emails have accounts.
 */
class LoginService
{
    private static ?string $decoyHash = null;

    public function attempt(string $email, string $password): ?User
    {
        $user = User::query()->where('email', Str::lower(trim($email)))->first();

        // Always check a password, even for an unknown email, so the response time does not give it away.
        $passwordMatches = Hash::check($password, $user->password ?? self::decoyHash());

        if (! $user instanceof User) {
            return null;
        }

        $user = $this->clearExpiredLock($user);

        if ($user->locked_until !== null) {
            return null;
        }

        if (! $passwordMatches) {
            $this->recordFailure($user);

            return null;
        }

        if (! $user->is_active) {
            return null;
        }

        $this->resetFailures($user);

        return $user;
    }

    /**
     * Forget past failures after a proven identity, for example a completed password reset.
     */
    public function resetFailures(User $user): void
    {
        if ($user->failed_logins === 0 && $user->locked_until === null) {
            return;
        }

        $user->failed_logins = 0;
        $user->locked_until = null;
        $user->save();
    }

    private function clearExpiredLock(User $user): User
    {
        if ($user->locked_until !== null && $user->locked_until->isPast()) {
            $this->resetFailures($user);
        }

        return $user;
    }

    private function recordFailure(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->failed_logins++;

            if ($locked->failed_logins >= (int) config('hdtms.auth.max_failed_logins')) {
                $locked->locked_until = now()->addMinutes((int) config('hdtms.auth.lockout_minutes'));

                Log::warning('Account locked after repeated failed sign-ins.', ['user_id' => $locked->id]);
            }

            $locked->save();

            $user->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private static function decoyHash(): string
    {
        return self::$decoyHash ??= Hash::make(Str::random(32));
    }
}
