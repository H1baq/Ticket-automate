<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Events\UserCreated;
use App\Models\User;
use App\Support\UserFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(UserFilters $filters, int $perPage): LengthAwarePaginator
    {
        $query = User::query();

        if ($filters->search !== null && $filters->search !== '') {
            $this->applySearch($query, $filters->search);
        }
        if ($filters->role instanceof UserRole) {
            $query->where('role', $filters->role);
        }
        if ($filters->active !== null) {
            $query->where('is_active', $filters->active);
        }

        $sort = in_array($filters->sort, UserFilters::SORTABLE, true) ? $filters->sort : 'name';
        $direction = $filters->direction === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sort, $direction)->orderBy('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Create an account for someone else. The new user gets a random password nobody knows and an
     * email with a link to choose their own.
     *
     * @param  array{name: string, email: string, role: UserRole, department?: string|null}  $data
     */
    public function create(array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Str::random(40),
            'department' => $data['department'] ?? null,
        ]);
        $user->role = $data['role'];
        $user->is_active = true;
        $user->save();

        UserCreated::dispatch($user);

        return $user;
    }

    /**
     * Change the details an administrator may edit. The role is only changed when one is given.
     *
     * @param  array{name: string, department?: string|null, role?: UserRole}  $data
     */
    public function update(User $user, array $data): User
    {
        $user->name = $data['name'];
        $user->department = $data['department'] ?? null;

        if (isset($data['role'])) {
            $user->role = $data['role'];
        }

        $user->save();

        return $user;
    }

    /**
     * Accounts are deactivated, never deleted, because tickets and their history point at them.
     * A deactivated user is signed out at once.
     */
    public function deactivate(User $user): User
    {
        $user->is_active = false;
        $user->save();

        $this->endSessions($user);

        return $user;
    }

    public function reactivate(User $user): User
    {
        $user->is_active = true;
        $user->save();

        return $user;
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applySearch(Builder $query, string $term): void
    {
        // "!" is the escape character, so a typed % or _ is searched for literally instead of acting as a wildcard.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($term)).'%';

        $query->where(function (Builder $inner) use ($like): void {
            $inner->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$like]);
        });
    }
}
