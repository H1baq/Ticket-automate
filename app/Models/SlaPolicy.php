<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketPriority;
use Database\Factories\SlaPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property TicketPriority $priority
 * @property int $resolution_hours
 */
class SlaPolicy extends Model
{
    /** @use HasFactory<SlaPolicyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'priority',
        'resolution_hours',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'resolution_hours' => 'integer',
        ];
    }
}
