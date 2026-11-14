<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only when the signed-in user has one of the given roles: role:admin or role:agent,admin.
 * It is the coarse gate on a route group; policies still decide each individual action.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(
            fn (string $role): UserRole => UserRole::tryFrom($role)
                ?? throw new LogicException("Unknown role [{$role}] in route middleware."),
            $roles,
        );

        $user = $request->user();

        abort_unless($user instanceof User && in_array($user->role, $allowed, true), 403);

        return $next($request);
    }
}
