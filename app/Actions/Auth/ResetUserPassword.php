<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Rules\PasswordPolicy;
use App\Services\LoginService;
use App\Services\UserService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Called by Fortify once the reset link is proven valid. Setting a new password also proves the person
 * owns the email address, so it clears any lockout, and it ends every other session of that user.
 */
class ResetUserPassword implements ResetsUserPasswords
{
    public function __construct(private readonly LoginService $logins, private readonly UserService $users)
    {
    }

    /**
     * @param  User  $user
     * @param  array<string, mixed>  $input
     */
    public function reset($user, array $input): void
    {
        Validator::make($input, [
            'password' => [...PasswordPolicy::rules(), 'confirmed'],
        ])->validate();

        $user->password = (string) $input['password'];
        $user->setRememberToken(Str::random(60));
        $user->save();

        $this->logins->resetFailures($user);
        $this->users->endSessions($user);
    }
}
