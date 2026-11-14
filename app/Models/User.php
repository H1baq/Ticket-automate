<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\RoutesNotifications;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property UserRole $role
 * @property string|null $department
 * @property bool $is_active
 * @property int $failed_logins
 * @property CarbonImmutable|null $locked_until
 * @property CarbonImmutable $created_at
 * @property-read int|null $open_tickets_count Only present when loaded with withCount, as the agent picker does.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    // Only notify() and mail routing; our own notifications() relation replaces Laravel's database one.
    use RoutesNotifications;

    /**
     * Role, activation and lockout fields are not mass assignable on purpose:
     * services set them explicitly so request data can never change them.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'failed_logins' => 'integer',
            'locked_until' => 'immutable_datetime',
        ];
    }

    /**
     * Agents who can be given tickets: the role is agent and the account is not deactivated.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function activeAgents(Builder $query): void
    {
        $query->where('role', UserRole::Agent)->where('is_active', true);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'agent_id');
    }

    /**
     * @return HasMany<Response, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
