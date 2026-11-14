<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\NotificationChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Response;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class SupportModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_names_are_unique_and_tickets_are_reachable(): void
    {
        $category = Category::factory()->create(['name' => 'Network']);
        $ticket = Ticket::factory()->for($category)->create();

        $this->assertTrue($category->tickets()->whereKey($ticket->id)->exists());
        $this->assertFalse(Category::factory()->inactive()->make()->is_active);

        $this->expectException(QueryException::class);
        Category::factory()->create(['name' => 'Network']);
    }

    public function test_sla_policy_casts_priority_and_is_unique_per_priority(): void
    {
        $policy = SlaPolicy::factory()->create(['priority' => TicketPriority::High, 'resolution_hours' => 4])->fresh();

        $this->assertSame(TicketPriority::High, $policy->priority);
        $this->assertSame(4, $policy->resolution_hours);

        $this->expectException(QueryException::class);
        SlaPolicy::factory()->create(['priority' => TicketPriority::High]);
    }

    public function test_response_belongs_to_a_ticket_and_an_author(): void
    {
        $response = Response::factory()->internal()->create();
        $response = Response::query()->with(['ticket', 'user'])->findOrFail($response->id);

        $this->assertTrue($response->is_internal);
        $this->assertInstanceOf(Ticket::class, $response->ticket);
        $this->assertInstanceOf(User::class, $response->user);
    }

    public function test_history_rows_record_status_changes_and_system_actions(): void
    {
        $byUser = TicketHistory::factory()->create([
            'action' => 'status_changed',
            'old_status' => TicketStatus::Assigned,
            'new_status' => TicketStatus::InProgress,
        ])->fresh();
        $bySystem = TicketHistory::factory()->bySystem()->create(['action' => 'escalated']);
        $loaded = TicketHistory::query()->with(['ticket', 'changedBy'])->findOrFail($bySystem->id);

        $this->assertSame(TicketStatus::Assigned, $byUser->old_status);
        $this->assertSame(TicketStatus::InProgress, $byUser->new_status);
        $this->assertNull($loaded->changedBy);
        $this->assertInstanceOf(Ticket::class, $loaded->ticket);
    }

    public function test_history_cannot_be_updated(): void
    {
        $history = TicketHistory::factory()->create();

        $this->expectException(LogicException::class);

        $history->update(['details' => 'edited later']);
    }

    public function test_history_cannot_be_deleted(): void
    {
        $history = TicketHistory::factory()->create();

        $this->expectException(LogicException::class);

        $history->delete();
    }

    public function test_notifications_track_channel_and_read_state(): void
    {
        $unread = Notification::factory()->create();
        $read = Notification::factory()->read()->email()->create()->fresh();

        $this->assertSame(NotificationChannel::Email, $read->channel);
        $this->assertNotNull($read->read_at);
        $this->assertSame([$unread->id], Notification::query()->unread()->pluck('id')->all());
    }
}
