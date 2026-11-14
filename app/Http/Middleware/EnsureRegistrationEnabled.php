<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Self-registration is a setting. The routes always exist, but answer 404 while it is off.
 */
class EnsureRegistrationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('hdtms.auth.self_registration') === true, 404);

        return $next($request);
    }
}
