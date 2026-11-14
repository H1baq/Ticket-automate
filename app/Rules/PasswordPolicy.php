<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Validation\Rules\Password;

/**
 * The one definition of an acceptable password: at least 8 characters (configurable) and at most 72,
 * which is all that bcrypt uses. Every form that sets a password takes its rules from here.
 */
final class PasswordPolicy
{
    private const BCRYPT_LIMIT = 72;

    /**
     * @return list<mixed>
     */
    public static function rules(): array
    {
        return [
            'required',
            'string',
            Password::min((int) config('hdtms.auth.password_min_length'))->max(self::BCRYPT_LIMIT),
        ];
    }
}
