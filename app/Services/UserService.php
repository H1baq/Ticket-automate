<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserService
{
    /**
     * Create the account of someone who signs themselves up. They always start as a staff user;
     * only an administrator can give a higher role.
     *
     * @param  array{name: string, email: string, password: string, department?: string|null}  $data
     */
    public function register(array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'department' => $data['department'] ?? null,
        ]);
        $user->role = UserRole::User;
        $user->is_active = true;
        $user->save();

        return $user;
    }

    /**
     * Sign a user out everywhere. Sessions live in the database, so this ends them at once.
     */
    public function endSessions(User $user): void
    {
        if (config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'))) {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
