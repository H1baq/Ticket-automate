<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $ticket_no
 * @property int $requester_id
 * @property int|null $agent_id
 * @property int $category_id
 * @property string $title
 * @property string $description
 * @property TicketPriority $priority
 * @property TicketStatus $status
 * @property string|null $attachment_path
 * @property CarbonImmutable $due_at
 * @property int $escalation_level
 * @property CarbonImmutable|null $escalated_at
 * @property string|null $resolution
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * Defaults that match the database, so a new model reports them before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'escalation_level' => 0,
    ];

    /**
     * Only the fields a ticket is created with. Agent, escalation and
     * resolution fields are set explicitly by the services that own them.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ticket_no',
        'requester_id',
        'category_id',
        'title',
        'description',
        'priority',
        'status',
        'attachment_path',
        'due_at',
    ];

    /**
     * Tickets are addressed by their number (/tickets/HD-2026-00142), which people quote to the help desk.
     */
    public function getRouteKeyName(): string
    {
        return 'ticket_no';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'due_at' => 'immutable_datetime',
            'escalation_level' => 'integer',
            'escalated_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Response, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    /**
     * @return HasMany<TicketHistory, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(TicketHistory::class);
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Tickets that still need work: open, assigned or in progress.
     *
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', self::values(TicketStatus::unresolved()));
    }

    /**
     * Unresolved tickets whose due time has passed.
     *
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->whereIn('status', self::values(TicketStatus::unresolved()))
            ->where('due_at', '<', now());
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function forAgent(Builder $query, User|int $agent): void
    {
        $query->where('agent_id', $agent instanceof User ? $agent->id : $agent);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function forRequester(Builder $query, User|int $requester): void
    {
        $query->where('requester_id', $requester instanceof User ? $requester->id : $requester);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, TicketStatus::unresolved(), true)
            && $this->due_at->isPast();
    }

    /**
     * @param  list<TicketStatus>  $statuses
     * @return list<string>
     */
    private static function values(array $statuses): array
    {
        return array_map(fn (TicketStatus $status): string => $status->value, $statuses);
    }
}
