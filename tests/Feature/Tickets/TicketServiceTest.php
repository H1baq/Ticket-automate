<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use App\Services\TicketService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SlaPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CategorySeeder::class, SlaPolicySeeder::class]);
        $this->requester = User::factory()->create();
        $this->category = Category::query()->where('name', 'Network')->firstOrFail();
        Storage::fake('local');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{title: string, description: string, category_id: int, priority: TicketPriority}
     */
    private function input(array $overrides = []): array
    {
        return [
            'title' => 'Wi-Fi keeps disconnecting',
            'description' => 'It drops every ten minutes in the training room.',
            'category_id' => $this->category->id,
            'priority' => TicketPriority::Medium,
            ...$overrides,
        ];
    }

    private function service(): TicketService
    {
        return app(TicketService::class);
    }

    public function test_a_ticket_is_created_open_with_the_requesters_details(): void
    {
        Event::fake([TicketCreated::class]);

        $ticket = $this->service()->create($this->requester, $this->input());

        $this->assertSame('HD-'.now()->year.'-00001', $ticket->ticket_no);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertSame($this->requester->id, $ticket->requester_id);
        $this->assertNull($ticket->agent_id);
        $this->assertSame($this->category->id, $ticket->category_id);
        $this->assertSame('Wi-Fi keeps disconnecting', $ticket->title);
        $this->assertSame(TicketPriority::Medium, $ticket->priority);
        $this->assertSame(0, $ticket->escalation_level);
        $this->assertNull($ticket->attachment_path);
        $this->assertNull($ticket->resolved_at);
    }

    public function test_the_due_time_is_the_sla_resolution_time_of_the_priority(): void
    {
        $this->travelTo(now()->startOfHour());

        $expected = ['high' => 4, 'medium' => 8, 'low' => 24];
        foreach (TicketPriority::cases() as $priority) {
            $ticket = $this->service()->create($this->requester, $this->input(['priority' => $priority]));

            $this->assertTrue(
                $ticket->due_at->equalTo(now()->addHours($expected[$priority->value])),
                "{$priority->value} should be due in {$expected[$priority->value]} hours",
            );
        }
    }

    public function test_changing_an_sla_time_affects_only_tickets_created_afterwards(): void
    {
        $this->travelTo(now()->startOfHour());
        $before = $this->service()->create($this->requester, $this->input(['priority' => TicketPriority::High]));

        SlaPolicy::query()->where('priority', TicketPriority::High)->update(['resolution_hours' => 2]);
        $after = $this->service()->create($this->requester, $this->input(['priority' => TicketPriority::High]));

        $this->assertTrue($before->fresh()->due_at->equalTo(now()->addHours(4)));
        $this->assertTrue($after->due_at->equalTo(now()->addHours(2)));
    }

    public function test_a_created_history_row_records_who_submitted_it(): void
    {
        $ticket = $this->service()->create($this->requester, $this->input(['priority' => TicketPriority::High]));

        $history = TicketHistory::query()->where('ticket_id', $ticket->id)->sole();
        $this->assertSame('created', $history->action);
        $this->assertSame($this->requester->id, $history->changed_by);
        $this->assertNull($history->old_status);
        $this->assertSame(TicketStatus::Open, $history->new_status);
        $this->assertSame('Ticket submitted with high priority.', $history->details);
    }

    public function test_the_ticket_created_event_is_dispatched_once_after_the_ticket_is_saved(): void
    {
        Event::fake([TicketCreated::class]);

        $ticket = $this->service()->create($this->requester, $this->input());

        Event::assertDispatchedTimes(TicketCreated::class, 1);
        Event::assertDispatched(TicketCreated::class, fn (TicketCreated $event): bool => $event->ticket->is($ticket) && Ticket::query()->whereKey($event->ticket->id)->exists());
    }

    public function test_the_event_is_dispatched_only_after_the_transaction_commits(): void
    {
        $sawCommittedTicket = null;
        Event::listen(TicketCreated::class, function (TicketCreated $event) use (&$sawCommittedTicket): void {
            $sawCommittedTicket = \Illuminate\Support\Facades\DB::transactionLevel();
        });

        $this->service()->create($this->requester, $this->input());

        // The test itself runs inside one wrapping transaction (level 1); the service's own transaction is over.
        $this->assertSame(1, $sawCommittedTicket);
    }

    // --- numbering ------------------------------------------------------------------------

    public function test_numbers_count_up_from_one_with_five_digits(): void
    {
        $numbers = [];
        for ($i = 0; $i < 12; $i++) {
            $numbers[] = $this->service()->create($this->requester, $this->input())->ticket_no;
        }

        $year = now()->year;
        $this->assertSame("HD-{$year}-00001", $numbers[0]);
        $this->assertSame("HD-{$year}-00009", $numbers[8]);
        $this->assertSame("HD-{$year}-00012", $numbers[11]);
    }

    public function test_fifty_tickets_created_in_a_loop_all_get_different_consecutive_numbers(): void
    {
        $numbers = [];
        for ($i = 0; $i < 50; $i++) {
            $numbers[] = $this->service()->create($this->requester, $this->input())->ticket_no;
        }

        $this->assertCount(50, array_unique($numbers));
        $this->assertSame(
            array_map(fn (int $n): string => sprintf('HD-%d-%05d', now()->year, $n), range(1, 50)),
            $numbers,
        );
    }

    public function test_numbering_continues_from_the_highest_number_not_the_count(): void
    {
        Ticket::factory()->create(['ticket_no' => 'HD-'.now()->year.'-00041']);

        $ticket = $this->service()->create($this->requester, $this->input());

        $this->assertSame('HD-'.now()->year.'-00042', $ticket->ticket_no);
    }

    public function test_each_year_starts_again_at_one_and_other_years_are_ignored(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 31)->setTime(23, 30));
        Ticket::factory()->create(['ticket_no' => 'HD-2025-00500']);
        $last2026 = $this->service()->create($this->requester, $this->input());

        $this->travelTo(now()->setDate(2027, 1, 1)->setTime(0, 15));
        $first2027 = $this->service()->create($this->requester, $this->input());
        $second2027 = $this->service()->create($this->requester, $this->input());

        $this->assertSame('HD-2026-00001', $last2026->ticket_no, 'The 2025 tickets must not affect 2026.');
        $this->assertSame('HD-2027-00001', $first2027->ticket_no);
        $this->assertSame('HD-2027-00002', $second2027->ticket_no);
    }

    public function test_the_year_follows_the_nairobi_calendar(): void
    {
        // 21:30 UTC on 31 December is already 00:30 on 1 January in Nairobi (UTC+3).
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-12-31 21:30:00', 'UTC'));

        $ticket = $this->service()->create($this->requester, $this->input());

        $this->assertSame('HD-2027-00001', $ticket->ticket_no);
    }

    public function test_a_year_cannot_pass_99999_tickets(): void
    {
        Ticket::factory()->create(['ticket_no' => 'HD-'.now()->year.'-99999']);

        try {
            $this->service()->create($this->requester, $this->input());
            $this->fail('A 100,000th ticket number must be refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('used up', $exception->getMessage());
        }

        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('ticket_history', 0);
    }

    // --- atomicity and attachments --------------------------------------------------------

    public function test_a_missing_sla_policy_creates_nothing_and_leaves_no_file_behind(): void
    {
        Event::fake([TicketCreated::class]);
        SlaPolicy::query()->where('priority', TicketPriority::Medium)->delete();

        try {
            $this->service()->create($this->requester, $this->input(), UploadedFile::fake()->create('photo.png', 20, 'image/png'));
            $this->fail('Creation without an SLA policy must fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('No SLA policy', $exception->getMessage());
        }

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_history', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        Event::assertNotDispatched(TicketCreated::class);
    }

    public function test_if_the_history_row_cannot_be_written_the_ticket_is_not_saved_either(): void
    {
        Event::fake([TicketCreated::class]);
        TicketHistory::creating(function (): never {
            throw new RuntimeException('History write failed.');
        });

        try {
            $this->service()->create($this->requester, $this->input(), UploadedFile::fake()->create('scan.pdf', 20, 'application/pdf'));
            $this->fail('The failure must reach the caller.');
        } catch (RuntimeException $exception) {
            $this->assertSame('History write failed.', $exception->getMessage());
        }

        $this->assertDatabaseCount('tickets', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        Event::assertNotDispatched(TicketCreated::class);
    }

    public function test_an_attachment_is_stored_privately_under_a_random_name(): void
    {
        $ticket = $this->service()->create($this->requester, $this->input(), UploadedFile::fake()->create('My Salary Slip.pdf', 100, 'application/pdf'));

        $this->assertNotNull($ticket->attachment_path);
        Storage::disk('local')->assertExists($ticket->attachment_path);
        $this->assertMatchesRegularExpression('#^tickets/\d{4}/\d{2}/[A-Za-z0-9]{40}\.pdf$#', $ticket->attachment_path);
        $this->assertStringNotContainsString('Salary', $ticket->attachment_path);
        $this->assertSame($ticket->attachment_path, $ticket->fresh()->attachment_path);
    }

    public function test_two_attachments_never_share_a_stored_name(): void
    {
        $first = $this->service()->create($this->requester, $this->input(), UploadedFile::fake()->image('same.png'));
        $second = $this->service()->create($this->requester, $this->input(), UploadedFile::fake()->image('same.png'));

        $this->assertNotSame($first->attachment_path, $second->attachment_path);
    }

    public function test_the_extension_comes_from_the_file_content_not_the_name_sent_by_the_browser(): void
    {
        $png = UploadedFile::fake()->image('holiday.jpg');

        $ticket = $this->service()->create($this->requester, $this->input(), $png);

        $this->assertStringEndsWith('.'.($png->guessExtension() ?? 'bin'), (string) $ticket->attachment_path);
    }
}
