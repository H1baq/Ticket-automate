<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MyTicketsTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->me = User::factory()->create(['name' => 'Wanjiku Kamau']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function mine(array $attributes = []): Ticket
    {
        return Ticket::factory()->for($this->me, 'requester')->create($attributes);
    }

    // --- access ---------------------------------------------------------------------------

    public function test_a_visitor_is_sent_to_sign_in(): void
    {
        $this->get('/tickets')->assertRedirect('/login');
    }

    public function test_agents_and_administrators_have_no_my_tickets_page(): void
    {
        $this->actingAs(User::factory()->agent()->create())->get('/tickets')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/tickets')->assertForbidden();
    }

    public function test_staff_see_the_my_tickets_menu_item_and_search_box(): void
    {
        $this->actingAs($this->me)->get('/tickets')
            ->assertOk()
            ->assertSee('href="'.route('tickets.index').'"', false)
            ->assertSee('data-shortcut-search', false);
    }

    public function test_agents_and_admins_are_not_offered_a_search_box_that_leads_to_a_403(): void
    {
        $this->actingAs(User::factory()->agent()->create())->get('/dashboard')->assertOk()->assertDontSee('action="'.route('tickets.index').'"', false);
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertOk()->assertDontSee('action="'.route('tickets.index').'"', false);
    }

    // --- privacy --------------------------------------------------------------------------

    public function test_a_user_sees_only_their_own_tickets(): void
    {
        $this->mine(['title' => 'My printer is jammed']);
        Ticket::factory()->create(['title' => 'Someone elses secret problem']);

        $this->actingAs($this->me)->get('/tickets')->assertOk()->assertSee('My printer is jammed')->assertDontSee('Someone elses secret problem');
    }

    public function test_no_filter_or_search_can_reach_another_users_tickets(): void
    {
        $this->mine(['title' => 'Mine']);
        $other = Ticket::factory()->create(['title' => 'Confidential payroll issue', 'ticket_no' => 'HD-2026-00777']);

        foreach (['?search=payroll', '?search=HD-2026-00777', '?search=confidential&status=unresolved', '?category='.$other->category_id, '?priority='.$other->priority->value] as $query) {
            $this->actingAs($this->me)->get('/tickets'.$query)->assertOk()->assertDontSee('Confidential payroll issue')->assertDontSee('data-copy="HD-2026-00777"', false);
        }
    }

    public function test_the_page_shows_the_details_of_each_ticket(): void
    {
        $category = Category::factory()->create(['name' => 'Printing']);
        $this->mine(['ticket_no' => 'HD-2026-00142', 'title' => 'Printer by the registry', 'category_id' => $category->id, 'priority' => TicketPriority::High, 'status' => TicketStatus::InProgress]);

        $this->actingAs($this->me)->get('/tickets')
            ->assertOk()
            ->assertSee('HD-2026-00142')
            ->assertSee('Printer by the registry')
            ->assertSee('Printing')
            ->assertSee('In progress')
            ->assertSee('High');
    }

    // --- overdue --------------------------------------------------------------------------

    public function test_overdue_tickets_are_flagged_in_words_and_in_the_row(): void
    {
        $this->mine(['title' => 'Late one'])->forceFill(['due_at' => now()->subHours(3)])->save();

        $this->actingAs($this->me)->get('/tickets')->assertSee('Overdue')->assertSee('is-overdue', false);
    }

    public function test_a_ticket_that_is_not_late_or_already_resolved_is_not_flagged(): void
    {
        $this->mine(['due_at' => now()->addHours(3)]);
        $this->mine(['status' => TicketStatus::Resolved, 'due_at' => now()->subDay(), 'resolved_at' => now()]);
        $this->mine(['status' => TicketStatus::Closed, 'due_at' => now()->subDay(), 'closed_at' => now()]);

        $this->actingAs($this->me)->get('/tickets')->assertDontSee('is-overdue', false)->assertDontSee('overdue-flag', false);
    }

    // --- summary --------------------------------------------------------------------------

    public function test_the_summary_counts_come_from_the_database_for_this_user_only(): void
    {
        $this->mine(['status' => TicketStatus::Open]);
        $this->mine(['status' => TicketStatus::Assigned]);
        $this->mine(['status' => TicketStatus::InProgress, 'due_at' => now()->subHour()]);
        $this->mine(['status' => TicketStatus::Resolved, 'due_at' => now()->subHour()]);
        $this->mine(['status' => TicketStatus::Closed]);
        $this->mine(['status' => TicketStatus::Closed]);
        Ticket::factory()->count(5)->create();

        $this->assertSame(
            ['unresolved' => 3, 'overdue' => 1, 'resolved' => 1, 'closed' => 2],
            app(TicketService::class)->summaryFor($this->me),
        );
    }

    public function test_the_summary_is_all_zero_for_someone_with_no_tickets(): void
    {
        $this->assertSame(['unresolved' => 0, 'overdue' => 0, 'resolved' => 0, 'closed' => 0], app(TicketService::class)->summaryFor($this->me));
    }

    public function test_the_stat_tiles_link_to_the_matching_filtered_list(): void
    {
        $this->actingAs($this->me)->get('/tickets')
            ->assertSee('status=unresolved', false)
            ->assertSee('overdue=1', false)
            ->assertSee('status=resolved', false)
            ->assertSee('status=closed', false);
    }

    public function test_the_overdue_tile_turns_red_only_when_something_is_overdue(): void
    {
        $this->actingAs($this->me)->get('/tickets')->assertDontSee('stat-tile is-link is-overdue', false);

        $this->mine()->forceFill(['due_at' => now()->subHour()])->save();
        $this->actingAs($this->me)->get('/tickets')->assertSee('is-overdue', false);
    }

    // --- filters --------------------------------------------------------------------------

    public function test_search_finds_a_title_or_a_ticket_number_in_any_case(): void
    {
        $this->mine(['title' => 'Wi-Fi keeps dropping', 'ticket_no' => 'HD-2026-00011']);
        $this->mine(['title' => 'Monitor flickers', 'ticket_no' => 'HD-2026-00012']);

        $this->actingAs($this->me)->get('/tickets?search=WI-FI')->assertSee('Wi-Fi keeps dropping')->assertDontSee('Monitor flickers');
        $this->actingAs($this->me)->get('/tickets?search=00012')->assertSee('Monitor flickers')->assertDontSee('Wi-Fi keeps dropping');
    }

    public function test_search_treats_wildcards_literally_and_is_safe_from_injection(): void
    {
        $this->mine(['title' => '100% broken']);
        $this->mine(['title' => 'Everything else']);

        $this->actingAs($this->me)->get('/tickets?search=%25')->assertSee('100% broken')->assertDontSee('Everything else');
        $this->actingAs($this->me)->get('/tickets?search='.urlencode("' OR 1=1 --"))->assertSee('No tickets match these filters')->assertDontSee('Everything else');
    }

    public function test_the_status_priority_category_and_overdue_filters_work_alone_and_together(): void
    {
        $network = Category::factory()->create(['name' => 'Network']);
        $this->mine(['title' => 'A open high network', 'status' => TicketStatus::Open, 'priority' => TicketPriority::High, 'category_id' => $network->id]);
        $this->mine(['title' => 'B closed high', 'status' => TicketStatus::Closed, 'priority' => TicketPriority::High, 'closed_at' => now()]);
        $this->mine(['title' => 'C open low', 'status' => TicketStatus::Open, 'priority' => TicketPriority::Low]);
        $this->mine(['title' => 'D late', 'status' => TicketStatus::InProgress, 'due_at' => now()->subHour()]);

        $this->actingAs($this->me)->get('/tickets?status=closed')->assertSee('B closed high')->assertDontSee('A open high network')->assertDontSee('C open low');
        $this->actingAs($this->me)->get('/tickets?status=unresolved')->assertSee('A open high network')->assertSee('C open low')->assertSee('D late')->assertDontSee('B closed high');
        $this->actingAs($this->me)->get('/tickets?priority=high')->assertSee('A open high network')->assertSee('B closed high')->assertDontSee('C open low');
        $this->actingAs($this->me)->get('/tickets?category='.$network->id)->assertSee('A open high network')->assertDontSee('B closed high');
        $this->actingAs($this->me)->get('/tickets?overdue=1')->assertSee('D late')->assertDontSee('A open high network');
        $this->actingAs($this->me)->get('/tickets?status=open&priority=high')->assertSee('A open high network')->assertDontSee('B closed high')->assertDontSee('C open low');
    }

    public function test_the_chosen_filters_stay_selected_in_the_form(): void
    {
        $this->actingAs($this->me)->get('/tickets?search=wifi&status=closed&priority=low&overdue=1')
            ->assertSee('value="wifi"', false)
            ->assertSee('<option value="closed" selected>Closed</option>', false)
            ->assertSee('<option value="low" selected>Low</option>', false)
            ->assertSee('checked', false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badQueries(): array
    {
        return [
            'unknown status' => ['status=maybe'],
            'unknown priority' => ['priority=urgent'],
            'unknown category' => ['category=99999'],
            'category not a number' => ['category=abc'],
            'bad sort column' => ['sort=password'],
            'sql in sort' => ['sort=title;drop table tickets'],
            'bad direction' => ['direction=sideways'],
            'search too long' => ['search=LONG'],
        ];
    }

    #[DataProvider('badQueries')]
    public function test_unexpected_parameters_are_refused_not_trusted(string $query): void
    {
        $query = str_replace('LONG', str_repeat('a', 101), $query);

        $this->actingAs($this->me)->from('/tickets')->get('/tickets?'.$query)->assertRedirect('/tickets');
    }

    public function test_the_filters_stay_in_the_page_links(): void
    {
        Ticket::factory()->count(30)->for($this->me, 'requester')->create(['priority' => TicketPriority::High]);

        $html = $this->actingAs($this->me)->get('/tickets?priority=high&sort=due_at&direction=asc')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/href="[^"]*page=2[^"]*priority=high|href="[^"]*priority=high[^"]*page=2/', $html);
        $this->assertStringContainsString('sort=due_at', $html);
    }

    // --- sorting --------------------------------------------------------------------------

    public function test_the_newest_ticket_comes_first_by_default(): void
    {
        $this->mine(['title' => 'Oldest ticket', 'created_at' => now()->subDays(3)]);
        $this->mine(['title' => 'Newest ticket', 'created_at' => now()->subHour()]);
        $this->mine(['title' => 'Middle ticket', 'created_at' => now()->subDay()]);

        $this->actingAs($this->me)->get('/tickets')->assertSeeInOrder(['Newest ticket', 'Middle ticket', 'Oldest ticket']);
    }

    public function test_priority_sorts_by_severity_not_alphabetically(): void
    {
        $this->mine(['title' => 'Low one', 'priority' => TicketPriority::Low]);
        $this->mine(['title' => 'High one', 'priority' => TicketPriority::High]);
        $this->mine(['title' => 'Medium one', 'priority' => TicketPriority::Medium]);

        $this->actingAs($this->me)->get('/tickets?sort=priority&direction=desc')->assertSeeInOrder(['High one', 'Medium one', 'Low one']);
        $this->actingAs($this->me)->get('/tickets?sort=priority&direction=asc')->assertSeeInOrder(['Low one', 'Medium one', 'High one']);
    }

    public function test_status_sorts_in_workflow_order(): void
    {
        $this->mine(['title' => 'Closed one', 'status' => TicketStatus::Closed, 'closed_at' => now()]);
        $this->mine(['title' => 'Open one', 'status' => TicketStatus::Open]);
        $this->mine(['title' => 'Progress one', 'status' => TicketStatus::InProgress]);
        $this->mine(['title' => 'Resolved one', 'status' => TicketStatus::Resolved, 'resolved_at' => now()]);
        $this->mine(['title' => 'Assigned one', 'status' => TicketStatus::Assigned]);

        $this->actingAs($this->me)->get('/tickets?sort=status&direction=asc')
            ->assertSeeInOrder(['Open one', 'Assigned one', 'Progress one', 'Resolved one', 'Closed one']);
    }

    public function test_title_and_due_time_sort_both_ways(): void
    {
        $this->mine(['title' => 'Bravo', 'due_at' => now()->addHours(5)]);
        $this->mine(['title' => 'Alpha', 'due_at' => now()->addHours(9)]);
        $this->mine(['title' => 'Charlie', 'due_at' => now()->addHours(1)]);

        $this->actingAs($this->me)->get('/tickets?sort=title&direction=asc')->assertSeeInOrder(['Alpha', 'Bravo', 'Charlie']);
        $this->actingAs($this->me)->get('/tickets?sort=title&direction=desc')->assertSeeInOrder(['Charlie', 'Bravo', 'Alpha']);
        $this->actingAs($this->me)->get('/tickets?sort=due_at&direction=asc')->assertSeeInOrder(['Charlie', 'Bravo', 'Alpha']);
    }

    // --- paging, empty states, performance, safety ------------------------------------------

    public function test_the_list_shows_twenty_tickets_a_page(): void
    {
        Ticket::factory()->count(45)->for($this->me, 'requester')->create();

        $this->actingAs($this->me)->get('/tickets')->assertSee('Showing 1–20 of 45 tickets');
        $this->actingAs($this->me)->get('/tickets?page=3')->assertSee('Showing 41–45 of 45 tickets');
    }

    public function test_someone_with_no_tickets_is_invited_to_report_a_problem(): void
    {
        $this->actingAs($this->me)->get('/tickets')
            ->assertOk()
            ->assertSee('You have not reported a problem yet')
            ->assertDontSee('<table', false)
            ->assertDontSee('No tickets match these filters');
    }

    public function test_a_search_with_no_result_says_so_and_offers_a_way_back(): void
    {
        $this->mine(['title' => 'Something']);

        $this->actingAs($this->me)->get('/tickets?search=zzzz')
            ->assertOk()
            ->assertSee('No tickets match these filters')
            ->assertSee('Clear filters')
            ->assertDontSee('You have not reported a problem yet');
    }

    public function test_a_full_page_needs_few_queries_because_categories_are_loaded_together(): void
    {
        Ticket::factory()->count(20)->for($this->me, 'requester')->create();

        DB::enableQueryLog();
        $this->actingAs($this->me)->get('/tickets')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 20 tickets and 20 different categories: one query per ticket would push this far past 15.
        $this->assertLessThanOrEqual(15, $queries, "The list used {$queries} queries.");
    }

    public function test_ticket_titles_are_escaped(): void
    {
        $this->mine(['title' => '<script>alert(1)</script>']);

        $this->actingAs($this->me)->get('/tickets')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_a_deactivated_user_cannot_open_the_list(): void
    {
        $this->actingAs(User::factory()->inactive()->create())->get('/tickets')->assertRedirect('/login');
    }
}
