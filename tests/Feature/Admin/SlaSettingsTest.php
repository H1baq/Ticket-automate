<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaService;
use App\Services\TicketService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SlaPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SlaSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([CategorySeeder::class, SlaPolicySeeder::class]);
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return ['high_hours' => 4, 'medium_hours' => 8, 'low_hours' => 24, ...$overrides];
    }

    /**
     * @return array<string, int>
     */
    private function stored(): array
    {
        return app(SlaService::class)->hoursByPriority();
    }

    // --- access ----------------------------------------------------------------------------

    public function test_staff_and_agents_get_403(): void
    {
        foreach ([User::factory()->create(), User::factory()->agent()->create()] as $user) {
            $this->actingAs($user)->get('/admin/sla')->assertForbidden();
            $this->actingAs($user)->put('/admin/sla', $this->input(['high_hours' => 1]))->assertForbidden();
        }

        $this->assertSame(['high' => 4, 'medium' => 8, 'low' => 24], $this->stored());
    }

    public function test_visitors_are_sent_to_sign_in_and_nothing_changes(): void
    {
        $this->get('/admin/sla')->assertRedirect('/login');
        $this->put('/admin/sla', $this->input(['high_hours' => 1]))->assertRedirect('/login');

        $this->assertSame(['high' => 4, 'medium' => 8, 'low' => 24], $this->stored());
    }

    public function test_a_deactivated_administrator_is_signed_out(): void
    {
        $this->actingAs(User::factory()->admin()->inactive()->create())->get('/admin/sla')->assertRedirect('/login');
    }

    public function test_every_sla_route_needs_sign_in_an_active_account_and_the_admin_role(): void
    {
        foreach (['admin.sla.edit', 'admin.sla.update'] as $name) {
            $middleware = app('router')->getRoutes()->getByName($name)->gatherMiddleware();

            foreach (['auth', 'active', 'role:admin'] as $required) {
                $this->assertContains($required, $middleware, "{$name} is missing {$required}");
            }
        }
    }

    // --- the page --------------------------------------------------------------------------

    public function test_the_page_shows_the_current_hours_and_says_when_a_change_applies(): void
    {
        $this->actingAs($this->admin)->get('/admin/sla')
            ->assertOk()
            ->assertSee('Resolution time by priority')
            ->assertSee('name="high_hours"', false)
            ->assertSee('value="4"', false)
            ->assertSee('value="8"', false)
            ->assertSee('value="24"', false)
            ->assertSee('after you save')
            ->assertSee('keep the due time they were given');
    }

    public function test_the_fields_are_whole_numbers_with_the_limits_in_the_markup(): void
    {
        $html = (string) $this->actingAs($this->admin)->get('/admin/sla')->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*type="number"[^>]*name="high_hours"|<input[^>]*name="high_hours"[^>]*type="number"/', $html);
        $this->assertStringContainsString('min="1"', $html);
        $this->assertStringContainsString('max="720"', $html);
        $this->assertStringContainsString('step="1"', $html);
    }

    public function test_the_menu_item_is_for_administrators_only(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')->assertSee('href="'.route('admin.sla.edit').'"', false);
        $this->actingAs(User::factory()->agent()->create())->get('/dashboard')->assertDontSee('SLA settings');
    }

    // --- saving ----------------------------------------------------------------------------

    public function test_the_administrator_saves_new_resolution_times(): void
    {
        $this->actingAs($this->admin)->from('/admin/sla')
            ->put('/admin/sla', $this->input(['high_hours' => 2, 'medium_hours' => 6, 'low_hours' => 48]))
            ->assertRedirect('/admin/sla')
            ->assertSessionHas('toast', ['type' => 'success', 'message' => 'Resolution times saved. They apply to tickets reported from now on.']);

        $this->assertSame(['high' => 2, 'medium' => 6, 'low' => 48], $this->stored());
        $this->actingAs($this->admin)->get('/admin/sla')->assertSee('value="48"', false);
    }

    public function test_saving_what_is_already_there_says_nothing_changed(): void
    {
        $this->actingAs($this->admin)->put('/admin/sla', $this->input())
            ->assertSessionHas('toast', ['type' => 'success', 'message' => 'Nothing changed. These are already the resolution times.']);
    }

    public function test_one_priority_can_change_without_touching_the_others(): void
    {
        $untouched = SlaPolicy::query()->where('priority', TicketPriority::Low)->value('updated_at');
        $this->travel(5)->minutes();

        $this->actingAs($this->admin)->put('/admin/sla', $this->input(['medium_hours' => 12]));

        $this->assertSame(['high' => 4, 'medium' => 12, 'low' => 24], $this->stored());
        $this->assertEquals($untouched, SlaPolicy::query()->where('priority', TicketPriority::Low)->value('updated_at'), 'Rows that did not change are not rewritten.');
    }

    // --- boundaries ------------------------------------------------------------------------

    public function test_one_hour_and_720_hours_are_accepted(): void
    {
        $this->actingAs($this->admin)->put('/admin/sla', $this->input(['high_hours' => 1, 'medium_hours' => 720, 'low_hours' => 720]))->assertSessionHasNoErrors();

        $this->assertSame(['high' => 1, 'medium' => 720, 'low' => 720], $this->stored());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidHours(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'just over the limit' => [721],
            'far over the limit' => [99999999999],
            'a decimal' => ['8.5'],
            'a fraction written out' => ['8,5'],
            'text' => ['eight'],
            'scientific notation' => ['1e2'],
            'empty' => [''],
            'a list' => [[8]],
        ];
    }

    #[DataProvider('invalidHours')]
    public function test_anything_but_a_whole_number_from_1_to_720_is_refused_and_nothing_is_saved(mixed $value): void
    {
        $this->actingAs($this->admin)->from('/admin/sla')
            ->put('/admin/sla', $this->input(['high_hours' => 2, 'medium_hours' => $value]))
            ->assertRedirect('/admin/sla')
            ->assertSessionHasErrors(['medium_hours' => 'Enter a whole number of hours from 1 to 720.']);

        $this->assertSame(['high' => 4, 'medium' => 8, 'low' => 24], $this->stored(), 'A refused form must not save even its valid fields.');
    }

    public function test_every_priority_is_required(): void
    {
        foreach (['high_hours', 'medium_hours', 'low_hours'] as $field) {
            $data = $this->input();
            unset($data[$field]);

            $this->actingAs($this->admin)->from('/admin/sla')->put('/admin/sla', $data)->assertSessionHasErrors($field);
        }

        $this->assertSame(['high' => 4, 'medium' => 8, 'low' => 24], $this->stored());
    }

    public function test_the_error_is_shown_next_to_the_field_and_the_typed_value_is_kept(): void
    {
        $this->actingAs($this->admin)->from('/admin/sla')->put('/admin/sla', $this->input(['high_hours' => 800]));

        $this->actingAs($this->admin)->get('/admin/sla')
            ->assertSee('Enter a whole number of hours from 1 to 720.')
            ->assertSee('value="800"', false);
    }

    // --- new tickets only ------------------------------------------------------------------

    public function test_new_times_apply_to_tickets_reported_afterwards_and_never_to_existing_ones(): void
    {
        $this->travelTo(now()->startOfHour());
        $reporter = User::factory()->create();
        $input = fn (): array => ['title' => 'Wi-Fi keeps disconnecting', 'description' => 'It drops every ten minutes.', 'category_id' => \App\Models\Category::query()->value('id'), 'priority' => TicketPriority::High];
        $before = app(TicketService::class)->create($reporter, $input());
        $dueBefore = $before->due_at;

        $this->actingAs($this->admin)->put('/admin/sla', $this->input(['high_hours' => 1]));
        $after = app(TicketService::class)->create($reporter, $input());

        $this->assertTrue($before->fresh()->due_at->equalTo($dueBefore), 'An existing ticket keeps its due time.');
        $this->assertTrue($before->fresh()->due_at->equalTo(now()->addHours(4)));
        $this->assertTrue($after->due_at->equalTo(now()->addHours(1)), 'A ticket reported after the change uses the new time.');
    }

    public function test_the_new_ticket_form_promises_the_saved_times(): void
    {
        $this->actingAs($this->admin)->put('/admin/sla', $this->input(['high_hours' => 3]));

        $this->actingAs($this->admin)->get('/tickets/create')->assertSee('We aim to resolve it within 3 hours.');
    }

    // --- locking order ---------------------------------------------------------------------

    public function test_the_rows_are_locked_and_updated_in_id_order(): void
    {
        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = ['sql' => strtolower($query->sql), 'bindings' => $query->bindings];
        });

        app(SlaService::class)->update(['high' => 2, 'medium' => 6, 'low' => 20], $this->admin);

        $locking = collect($statements)->first(fn (array $statement): bool => str_contains($statement['sql'], 'from `sla_policies`') && str_contains($statement['sql'], 'for update'));
        $this->assertNotNull($locking, 'The rows must be read with a lock.');
        $this->assertStringContainsString('order by `id` asc', $locking['sql'], 'The lock must be taken in id order, like ticket creation does.');

        $updatedIds = collect($statements)
            ->filter(fn (array $statement): bool => str_starts_with($statement['sql'], 'update `sla_policies`'))
            ->map(fn (array $statement): int => (int) end($statement['bindings']))
            ->values()
            ->all();
        $this->assertCount(3, $updatedIds);
        $sorted = $updatedIds;
        sort($sorted);
        $this->assertSame($sorted, $updatedIds, 'Rows must be updated in ascending id order.');
    }

    public function test_the_lock_comes_before_any_change(): void
    {
        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = strtolower($query->sql);
        });

        app(SlaService::class)->update(['high' => 2], $this->admin);

        $lockAt = collect($statements)->search(fn (string $sql): bool => str_contains($sql, 'for update'));
        $updateAt = collect($statements)->search(fn (string $sql): bool => str_starts_with($sql, 'update `sla_policies`'));
        $this->assertNotFalse($lockAt);
        $this->assertLessThan($updateAt, $lockAt);
    }

    public function test_a_missing_priority_row_is_created_instead_of_breaking_ticket_creation(): void
    {
        SlaPolicy::query()->where('priority', TicketPriority::Low)->delete();

        app(SlaService::class)->update(['high' => 4, 'medium' => 8, 'low' => 30], $this->admin);

        $this->assertSame(['high' => 4, 'medium' => 8, 'low' => 30], $this->stored());
    }

    public function test_a_change_is_logged_with_who_made_it_and_never_anything_sensitive(): void
    {
        $log = Log::spy();

        $this->actingAs($this->admin)->put('/admin/sla', $this->input(['high_hours' => 2]));

        $log->shouldHaveReceived('info')->withArgs(
            fn (string $message, array $context): bool => $message === 'SLA resolution times changed.'
                && $context === ['changed_by' => $this->admin->id, 'changes' => ['high' => ['from' => 4, 'to' => 2]]],
        )->once();
    }

    public function test_saving_nothing_new_writes_no_log_entry(): void
    {
        $log = Log::spy();

        $this->actingAs($this->admin)->put('/admin/sla', $this->input());

        $log->shouldNotHaveReceived('info');
    }

    public function test_saving_while_the_tickets_table_is_empty_or_full_makes_no_difference(): void
    {
        Ticket::factory()->count(5)->create();

        $this->actingAs($this->admin)->put('/admin/sla', $this->input(['low_hours' => 12]));

        $this->assertSame(12, $this->stored()['low']);
        $this->assertDatabaseCount('tickets', 5);
    }
}
