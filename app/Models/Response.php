<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\ResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property string $message
 * @property bool $is_internal
 */
class Response extends Model
{
    /** @use HasFactory<ResponseFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'is_internal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    /**
     * Internal notes are for the support team. Staff who reported the ticket never see them.
     *
     * @param  Builder<Response>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $viewer): void
    {
        if ($viewer->role === UserRole::User) {
            $query->where('is_internal', false);
        }
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
