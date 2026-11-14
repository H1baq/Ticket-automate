<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class AuthenticationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Our own routes (routes/auth.php) use Fortify's controllers, so each can carry our rate limits.
        Fortify::ignoreRoutes();
    }
}
