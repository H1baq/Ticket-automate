<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TicketHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only audit trail of everything that happens to a ticket.
 * A null changed_by means the system made the change.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $changed_by
 * @property string $action
 * @property TicketStatus|null $old_status
 * @property TicketStatus|null $new_status
 * @property string|null $details
 * @property CarbonImmutable $created_at
 */
class TicketHistory extends Model
{
    /** @use HasFactory<TicketHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'ticket_history';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'changed_by',
        'action',
        'old_status',
        'new_status',
        'details',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Ticket history is append-only and cannot be changed.');
        });

        static::deleting(function (): never {
            throw new LogicException('Ticket history is append-only and cannot be deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_status' => TicketStatus::class,
            'new_status' => TicketStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
