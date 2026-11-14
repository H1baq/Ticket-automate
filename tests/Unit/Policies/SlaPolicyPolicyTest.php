<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\SlaPolicyPolicy;
use PHPUnit\Framework\TestCase;

class SlaPolicyPolicyTest extends TestCase
{
    private function user(UserRole $role, bool $active = true): User
    {
        $user = new User();
        $user->id = 1;
        $user->role = $role;
        $user->is_active = $active;

        return $user;
    }

    public function test_only_an_active_administrator_may_see_or_change_resolution_times(): void
    {
        $policy = new SlaPolicyPolicy();

        $this->assertTrue($policy->viewAny($this->user(UserRole::Admin)));
        $this->assertTrue($policy->update($this->user(UserRole::Admin)));

        foreach ([$this->user(UserRole::Agent), $this->user(UserRole::User), $this->user(UserRole::Admin, false)] as $other) {
            $this->assertFalse($policy->viewAny($other), $other->role->value);
            $this->assertFalse($policy->update($other), $other->role->value);
        }
    }
}
