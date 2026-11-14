<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Resolution times decide every due time and every escalation, so only an active administrator may see or change them.
 */
class SlaPolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::Admin;
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }
}
