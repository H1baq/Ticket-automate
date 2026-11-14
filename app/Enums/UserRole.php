<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Agent = 'agent';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Staff user',
            self::Agent => 'Support agent',
            self::Admin => 'Administrator',
        };
    }
}
