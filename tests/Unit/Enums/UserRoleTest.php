<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_backing_values_match_the_database_enum(): void
    {
        $this->assertSame(['user', 'agent', 'admin'], array_column(UserRole::cases(), 'value'));
    }

    public function test_every_role_has_a_label(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertNotSame('', $role->label());
        }
    }
}
