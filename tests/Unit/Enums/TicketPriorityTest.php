<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\TicketPriority;
use PHPUnit\Framework\TestCase;

class TicketPriorityTest extends TestCase
{
    public function test_backing_values_match_the_database_enum(): void
    {
        $this->assertSame(['low', 'medium', 'high'], array_column(TicketPriority::cases(), 'value'));
    }

    public function test_every_priority_has_a_label_and_a_plain_description(): void
    {
        foreach (TicketPriority::cases() as $priority) {
            $this->assertNotSame('', $priority->label());
            $this->assertNotSame('', $priority->description());
        }
    }
}
