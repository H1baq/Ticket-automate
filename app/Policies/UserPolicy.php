<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Only administrators manage accounts. Nobody can change their own role or deactivate themselves,
 * so an administrator can never lock the last administrator out by accident.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isAdmin($actor);
    }

    public function create(User $actor): bool
    {
        return $this->isAdmin($actor);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->isAdmin($actor);
    }

    public function changeRole(User $actor, User $target): bool
    {
        return $this->isAdmin($actor) && ! $actor->is($target);
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $this->isAdmin($actor) && ! $actor->is($target) && $target->is_active;
    }

    public function reactivate(User $actor, User $target): bool
    {
        return $this->isAdmin($actor) && ! $target->is_active;
    }

    private function isAdmin(User $user): bool
    {
        return $user->role === UserRole::Admin && $user->is_active;
    }
}
