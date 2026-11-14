<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
        };
    }

    /**
     * 1 (low) to 3 (high), used to draw the priority bars.
     */
    public function level(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
        };
    }

    /**
     * A plain-words explanation shown next to the priority picker.
     */
    public function description(): string
    {
        return match ($this) {
            self::Low => 'Not urgent. I can keep working while this is looked at.',
            self::Medium => 'It slows my work down, but I can still get things done.',
            self::High => 'I cannot work, or many people are affected.',
        };
    }
}
