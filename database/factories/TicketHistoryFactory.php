<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketHistory>
 */
class TicketHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'changed_by' => User::factory(),
            'action' => 'created',
            'old_status' => null,
            'new_status' => null,
            'details' => null,
        ];
    }

    public function bySystem(): static
    {
        return $this->state(['changed_by' => null]);
    }
}
