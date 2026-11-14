<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_no' => sprintf('HD-%d-%05d', now()->year, fake()->unique()->numberBetween(1, 99999)),
            'requester_id' => User::factory(),
            'agent_id' => null,
            'category_id' => Category::factory(),
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(TicketPriority::cases()),
            'status' => TicketStatus::Open,
            'attachment_path' => null,
            'due_at' => now()->addHours(8),
            'escalation_level' => 0,
            'escalated_at' => null,
            'resolution' => null,
            'resolved_at' => null,
            'closed_at' => null,
        ];
    }

    public function assigned(?User $agent = null): static
    {
        return $this->state(fn (): array => [
            'agent_id' => $agent instanceof User ? $agent->id : User::factory()->agent(),
            'status' => TicketStatus::Assigned,
        ]);
    }

    public function inProgress(?User $agent = null): static
    {
        return $this->assigned($agent)->state(['status' => TicketStatus::InProgress]);
    }

    public function resolved(?User $agent = null): static
    {
        return $this->inProgress($agent)->state(fn (): array => [
            'status' => TicketStatus::Resolved,
            'resolution' => fake()->paragraph(),
            'resolved_at' => now(),
        ]);
    }

    public function closed(?User $agent = null): static
    {
        return $this->resolved($agent)->state(fn (): array => [
            'status' => TicketStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    public function priority(TicketPriority $priority): static
    {
        return $this->state(['priority' => $priority]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => ['due_at' => now()->subHours(2)]);
    }

    public function escalated(int $level = 1): static
    {
        return $this->state(fn (): array => [
            'escalation_level' => $level,
            'escalated_at' => now()->subMinutes(30),
        ]);
    }
}
