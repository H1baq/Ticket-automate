<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        RateLimiter::for('ticket-creation', function (Request $request): array {
            $key = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

            return [Limit::perMinute(5)->by($key), Limit::perHour(30)->by($key)];
        });
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
