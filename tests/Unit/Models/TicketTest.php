<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Response;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_enum_and_date_attributes_are_cast(): void
    {
        $ticket = Ticket::factory()->priority(TicketPriority::High)->create()->fresh();

        $this->assertSame(TicketPriority::High, $ticket->priority);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertTrue($ticket->due_at->isFuture());
        $this->assertSame(0, $ticket->escalation_level);
    }

    public function test_ticket_has_all_its_relations(): void
    {
        $requester = User::factory()->create();
        $agent = User::factory()->agent()->create();
        $category = Category::factory()->create();
        $ticket = Ticket::factory()
            ->for($requester, 'requester')
            ->for($category)
            ->assigned($agent)
            ->create();
        $response = Response::factory()->for($ticket)->create();
        $history = TicketHistory::factory()->for($ticket)->create();
        $notification = Notification::factory()->for($ticket)->create();

        $ticket = Ticket::query()
            ->with(['requester', 'agent', 'category', 'responses', 'history', 'notifications'])
            ->findOrFail($ticket->id);

        $this->assertTrue($ticket->requester->is($requester));
        $this->assertTrue($ticket->agent->is($agent));
        $this->assertTrue($ticket->category->is($category));
        $this->assertTrue($ticket->responses->contains($response));
        $this->assertTrue($ticket->history->contains($history));
        $this->assertTrue($ticket->notifications->contains($notification));
    }

    public function test_open_scope_returns_only_unresolved_tickets(): void
    {
        $open = Ticket::factory()->create();
        $assigned = Ticket::factory()->assigned()->create();
        $inProgress = Ticket::factory()->inProgress()->create();
        Ticket::factory()->resolved()->create();
        Ticket::factory()->closed()->create();

        $ids = Ticket::query()->open()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$open->id, $assigned->id, $inProgress->id], $ids);
    }

    public function test_overdue_scope_and_is_overdue_agree(): void
    {
        $late = Ticket::factory()->overdue()->create();
        $lateInProgress = Ticket::factory()->inProgress()->overdue()->create();
        $onTime = Ticket::factory()->create();
        $lateButResolved = Ticket::factory()->resolved()->overdue()->create();
        $lateButClosed = Ticket::factory()->closed()->overdue()->create();

        $ids = Ticket::query()->overdue()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$late->id, $lateInProgress->id], $ids);
        $this->assertTrue($late->isOverdue());
        $this->assertTrue($lateInProgress->isOverdue());
        $this->assertFalse($onTime->isOverdue());
        $this->assertFalse($lateButResolved->isOverdue());
        $this->assertFalse($lateButClosed->isOverdue());
    }

    public function test_ticket_becomes_overdue_exactly_after_its_due_time(): void
    {
        $this->travelTo(now()->startOfHour());
        $ticket = Ticket::factory()->create(['due_at' => now()->addHours(4)]);

        $this->travelTo(now()->addHours(4));
        $this->assertFalse($ticket->isOverdue());
        $this->assertFalse(Ticket::query()->overdue()->exists());

        $this->travel(1)->seconds();
        $this->assertTrue($ticket->isOverdue());
        $this->assertTrue(Ticket::query()->overdue()->exists());
    }

    public function test_for_agent_and_for_requester_scopes_accept_a_model_or_an_id(): void
    {
        $agent = User::factory()->agent()->create();
        $requester = User::factory()->create();
        $mine = Ticket::factory()->for($requester, 'requester')->assigned($agent)->create();
        Ticket::factory()->assigned()->create();

        $this->assertSame([$mine->id], Ticket::query()->forAgent($agent)->pluck('id')->all());
        $this->assertSame([$mine->id], Ticket::query()->forAgent($agent->id)->pluck('id')->all());
        $this->assertSame([$mine->id], Ticket::query()->forRequester($requester)->pluck('id')->all());
        $this->assertSame([$mine->id], Ticket::query()->forRequester($requester->id)->pluck('id')->all());
    }

    public function test_lazy_loading_a_relation_is_refused_outside_production(): void
    {
        Ticket::factory()->count(2)->create();
        $ticket = Ticket::query()->get()->firstOrFail();

        $this->expectException(LazyLoadingViolationException::class);

        $ticket->getRelationValue('requester');
    }

    public function test_agent_and_escalation_fields_cannot_be_mass_assigned(): void
    {
        $ticket = new Ticket();
        $ticket->fill(['agent_id' => 5, 'escalation_level' => 3, 'resolution' => 'done']);

        $this->assertNull($ticket->agent_id);
        $this->assertNull($ticket->resolution);
    }
}
