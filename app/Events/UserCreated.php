<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An administrator created an account. The new user still needs a way to choose a password.
 */
class UserCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly User $user)
    {
    }
}
