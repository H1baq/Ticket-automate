<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use PHPUnit\Framework\TestCase;

class UserPolicyTest extends TestCase
{
    private function user(int $id, UserRole $role, bool $active = true): User
    {
        $user = new User();
        $user->id = $id;
        $user->role = $role;
        $user->is_active = $active;

        return $user;
    }

    public function test_only_active_administrators_manage_accounts(): void
    {
        $policy = new UserPolicy();
        $target = $this->user(9, UserRole::User);

        foreach ([UserRole::User, UserRole::Agent] as $role) {
            $actor = $this->user(1, $role);
            $this->assertFalse($policy->viewAny($actor));
            $this->assertFalse($policy->create($actor));
            $this->assertFalse($policy->update($actor, $target));
            $this->assertFalse($policy->changeRole($actor, $target));
            $this->assertFalse($policy->deactivate($actor, $target));
        }

        $admin = $this->user(1, UserRole::Admin);
        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $target));
        $this->assertTrue($policy->changeRole($admin, $target));
        $this->assertTrue($policy->deactivate($admin, $target));
    }

    public function test_a_deactivated_administrator_has_no_powers(): void
    {
        $policy = new UserPolicy();
        $inactiveAdmin = $this->user(1, UserRole::Admin, false);

        $this->assertFalse($policy->viewAny($inactiveAdmin));
        $this->assertFalse($policy->create($inactiveAdmin));
    }

    public function test_nobody_changes_their_own_role_or_deactivates_themselves(): void
    {
        $policy = new UserPolicy();
        $admin = $this->user(1, UserRole::Admin);

        $this->assertFalse($policy->changeRole($admin, $admin));
        $this->assertFalse($policy->deactivate($admin, $admin));
        $this->assertTrue($policy->update($admin, $admin), 'An admin may still edit their own name and department.');
    }

    public function test_deactivate_and_reactivate_apply_to_the_right_state(): void
    {
        $policy = new UserPolicy();
        $admin = $this->user(1, UserRole::Admin);
        $active = $this->user(2, UserRole::User);
        $inactive = $this->user(3, UserRole::User, false);

        $this->assertTrue($policy->deactivate($admin, $active));
        $this->assertFalse($policy->deactivate($admin, $inactive));
        $this->assertTrue($policy->reactivate($admin, $inactive));
        $this->assertFalse($policy->reactivate($admin, $active));
    }
}
