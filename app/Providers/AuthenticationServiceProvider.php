<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Auth\DisableRememberMe;
use App\Actions\Auth\ResetUserPassword;
use App\Services\LoginService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Fortify;

class AuthenticationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Our own routes (routes/auth.php) use Fortify's controllers, so each can carry our rate limits.
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::authenticateUsing(
            fn (Request $request) => $this->app->make(LoginService::class)->attempt(
                (string) $request->input('email'),
                (string) $request->input('password'),
            ),
        );

        // Fortify's own throttle step is left out: the "login" limiter below guards the route, and the
        // account lockout in LoginService counts the failures that matter.
        Fortify::authenticateThrough(fn () => [
            DisableRememberMe::class,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(10)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by((string) $request->ip()),
        ]);

        RateLimiter::for('password-reset', fn (Request $request): array => [
            Limit::perMinute(5)->by((string) $request->ip()),
            Limit::perHour(10)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
        ]);

        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perHour(10)->by((string) $request->ip()));
    }
}
