<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserCreated;
use App\Notifications\SetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Password;

class SendSetPasswordLink implements ShouldQueue
{
    public int $tries = 3;

    public function handle(UserCreated $event): void
    {
        $token = Password::broker(config('fortify.passwords'))->createToken($event->user);

        $event->user->notify(new SetPasswordNotification($token));
    }
}
