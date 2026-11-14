<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Closure;
use Illuminate\Http\Request;

/**
 * Sessions end after 30 idle minutes. A "remember me" cookie would outlive that, so the flag is
 * dropped even when someone sends it by hand.
 */
class DisableRememberMe
{
    public function handle(Request $request, Closure $next): mixed
    {
        $request->request->remove('remember');
        $request->query->remove('remember');

        return $next($request);
    }
}
