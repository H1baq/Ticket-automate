<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Enums\NotificationChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;
use Throwable;

class ComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // In real requests the framework shares $errors; a bare rendered string has none.
        view()->share('errors', new ViewErrorBag());
    }

    /**
     * @param  list<string>  $names
     */
    private function defineRoutes(array $names): void
    {
        // Start from an empty route table so these tests do not depend on which pages the app has yet.
        Route::setRoutes(new RouteCollection());

        foreach ($names as $name) {
            Route::get('/_test/'.$name, fn (): string => 'ok')->name($name);
        }

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_status_badge_shows_the_status_as_text_with_its_own_class(): void
    {
        foreach (TicketStatus::cases() as $status) {
            $this->blade('<x-status-badge :status="$status" />', ['status' => $status])
                ->assertSee($status->label())
                ->assertSee('status-badge--'.str_replace('_', '-', $status->value), false);
        }
    }

    public function test_overdue_label_is_text_and_only_shown_when_overdue(): void
    {
        $this->blade('<x-status-badge :status="$status" :overdue="true" />', ['status' => TicketStatus::Open])
            ->assertSee('Overdue');
        $this->blade('<x-status-badge :status="$status" />', ['status' => TicketStatus::Open])
            ->assertDontSee('Overdue');
    }

    public function test_priority_badge_draws_as_many_bars_as_the_level_and_names_it(): void
    {
        foreach ([[TicketPriority::Low, 1], [TicketPriority::Medium, 2], [TicketPriority::High, 3]] as [$priority, $level]) {
            $html = (string) $this->blade('<x-priority-badge :priority="$priority" />', ['priority' => $priority]);

            $this->assertSame($level, substr_count($html, 'class="on"'));
            $this->assertStringContainsString($priority->label(), $html);
        }
    }

    public function test_ticket_number_has_a_labelled_copy_button(): void
    {
        $this->blade('<x-ticket-no number="HD-2026-00142" />')
            ->assertSee('data-copy="HD-2026-00142"', false)
            ->assertSee('aria-label="Copy ticket number HD-2026-00142"', false);
    }

    public function test_stat_tile_is_a_link_only_when_given_an_address(): void
    {
        $this->blade('<x-stat-tile label="Open" value="18" href="/tickets" />')
            ->assertSee('<a href="/tickets"', false);
        $this->blade('<x-stat-tile label="Open" value="18" />')
            ->assertDontSee('<a ', false)
            ->assertSee('18');
        $this->blade('<x-stat-tile label="Overdue" value="3" :overdue="true" />')
            ->assertSee('is-overdue', false);
    }

    public function test_user_text_is_escaped_everywhere(): void
    {
        $payload = '<script>alert(1)</script>';

        $this->blade('<x-page-header :title="$payload" />', ['payload' => $payload])
            ->assertDontSee($payload, false)
            ->assertSee('&lt;script&gt;', false);
        $this->blade('<x-form.input name="title" label="Title" :value="$payload" />', ['payload' => $payload])
            ->assertDontSee($payload, false);
        $this->blade('<x-empty-state :title="$payload" :text="$payload" />', ['payload' => $payload])
            ->assertDontSee($payload, false);
        $this->blade('<x-toast type="info" :message="$payload" />', ['payload' => $payload])
            ->assertDontSee($payload, false);
    }

    public function test_input_links_label_hint_and_error_for_assistive_technology(): void
    {
        $errors = (new ViewErrorBag())->put('default', new MessageBag(['title' => ['Enter at least 5 characters.']]));
        view()->share('errors', $errors);

        $this->blade('<x-form.input name="title" label="Title" hint="A short summary." :required="true" />')
            ->assertSee('for="field-title"', false)
            ->assertSee('aria-describedby="field-title-hint field-title-error"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('is-invalid', false)
            ->assertSee('Enter at least 5 characters.')
            ->assertSee('(required)')
            ->assertSee('id="field-title-error"', false);
    }

    public function test_input_without_an_error_is_not_marked_invalid(): void
    {
        $this->blade('<x-form.input name="title" label="Title" />')
            ->assertDontSee('aria-invalid', false)
            ->assertDontSee('is-invalid', false)
            ->assertDontSee('aria-describedby', false);
    }

    public function test_input_repopulates_from_old_input_but_never_for_passwords(): void
    {
        Route::middleware('web')->get('/_test/form', fn (): string => Blade::render(
            '<x-form.input name="title" label="Title" /><x-form.input name="password" label="Password" type="password" />',
        ));

        $this->withSession(['_old_input' => ['title' => 'Kept text', 'password' => 'secret-value']])
            ->get('/_test/form')
            ->assertOk()
            ->assertSee('value="Kept text"', false)
            ->assertDontSee('secret-value', false);
    }

    public function test_select_marks_the_chosen_option_and_offers_a_placeholder(): void
    {
        $this->blade('<x-form.select name="category" label="Category" :options="$options" value="net" placeholder="Choose" />', ['options' => ['hw' => 'Hardware', 'net' => 'Network']])
            ->assertSee('<option value="">Choose</option>', false)
            ->assertSee('<option value="net" selected>Network</option>', false)
            ->assertDontSee('<option value="hw" selected>', false);
    }

    public function test_textarea_shows_a_character_counter_when_limited(): void
    {
        $this->blade('<x-form.textarea name="description" label="Description" :maxlength="5000" value="Hello" />')
            ->assertSee('data-char-count-for="field-description"', false)
            ->assertSee('data-max="5000"', false)
            ->assertSee('maxlength="5000"', false)
            ->assertSee('>Hello</textarea>', false);
    }

    public function test_file_input_accepts_only_the_listed_types(): void
    {
        $this->blade('<x-form.file name="attachment" label="Attachment" accept=".jpg,.png,.pdf" hint="Up to 5 MB." />')
            ->assertSee('type="file"', false)
            ->assertSee('accept=".jpg,.png,.pdf"', false)
            ->assertSee('aria-describedby="field-attachment-hint"', false);
    }

    public function test_confirm_modal_carries_a_csrf_token_and_method_spoofing(): void
    {
        $this->blade('<x-modal-confirm id="confirm" title="Delete?" action="/x" method="DELETE" confirm-label="Delete">Sure?</x-modal-confirm>')
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('aria-labelledby="confirm-title"', false)
            ->assertSee('data-confirm-cancel', false)
            ->assertSee('Delete');
    }

    public function test_buttons_can_be_links_and_carry_loading_text(): void
    {
        $this->blade('<x-button href="/go">Go</x-button>')->assertSee('<a href="/go"', false);
        $this->blade('<x-button type="submit" loading-text="Saving…">Save</x-button>')
            ->assertSee('type="submit"', false)
            ->assertSee('data-loading-text="Saving…"', false);
    }

    public function test_toast_region_renders_a_flashed_toast_and_a_template_for_new_ones(): void
    {
        $this->withSession(['toast' => ['type' => 'success', 'message' => 'Ticket created.']]);

        $this->blade('<x-toast-region />')
            ->assertSee('aria-live="polite"', false)
            ->assertSee('Ticket created.')
            ->assertSee('data-toast-autoshow', false)
            ->assertSee('<template id="toast-template">', false);
    }

    public function test_toast_region_ignores_malformed_or_unknown_flashes(): void
    {
        $this->withSession(['toast' => ['type' => 'dangerous', 'message' => 'Hello']]);
        $this->blade('<x-toast-region />')->assertSee('toast-info', false);

        $this->withSession(['toast' => 'not an array']);
        $this->blade('<x-toast-region />')->assertDontSee('data-toast-autoshow', false);
    }

    public function test_errors_are_announced_as_alerts_and_stay_until_dismissed(): void
    {
        $this->blade('<x-toast type="error" message="Too big" />')
            ->assertSee('role="alert"', false)
            ->assertSee('data-bs-autohide="false"', false);
        $this->blade('<x-toast type="success" message="Saved" />')
            ->assertSee('role="status"', false)
            ->assertSee('data-bs-autohide="true"', false);
    }

    public function test_data_table_shows_the_empty_slot_instead_of_an_empty_table(): void
    {
        $this->blade(
            '<x-data-table caption="Tickets" :columns="[]" :is-empty="true"><x-slot:empty>Nothing here</x-slot:empty></x-data-table>',
        )->assertSee('Nothing here')->assertDontSee('<table', false);
    }

    public function test_pagination_summarises_the_range_and_marks_the_current_page(): void
    {
        $paginator = new LengthAwarePaginator(array_fill(0, 20, 'x'), 142, 20, 2, ['path' => '/tickets']);

        $this->blade('<x-pagination :paginator="$paginator" noun="tickets" />', ['paginator' => $paginator])
            ->assertSee('Showing 21–40 of 142 tickets')
            ->assertSee('aria-current="page"', false)
            ->assertSee('Previous')
            ->assertSee('Next');
    }

    public function test_pagination_is_hidden_when_there_is_nothing_to_page(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 20, 1, ['path' => '/tickets']);

        $this->blade('<x-pagination :paginator="$paginator" />', ['paginator' => $paginator])->assertDontSee('Showing');
    }

    public function test_pagination_on_a_single_page_has_no_page_buttons(): void
    {
        $paginator = new LengthAwarePaginator(array_fill(0, 5, 'x'), 5, 20, 1, ['path' => '/tickets']);

        $this->blade('<x-pagination :paginator="$paginator" noun="tickets" />', ['paginator' => $paginator])
            ->assertSee('Showing 1–5 of 5 tickets')
            ->assertDontSee('Next');
    }

    public function test_timeline_names_who_acted_and_labels_system_actions(): void
    {
        $agent = User::factory()->agent()->create(['name' => 'Brian Otieno']);
        $ticket = Ticket::factory()->create();
        TicketHistory::factory()->for($ticket)->create(['changed_by' => $agent->id, 'action' => 'status_changed', 'old_status' => TicketStatus::Assigned, 'new_status' => TicketStatus::InProgress]);
        TicketHistory::factory()->for($ticket)->bySystem()->create(['action' => 'escalated', 'details' => 'Escalated to level 1.']);

        $history = $ticket->history()->with('changedBy')->orderBy('id')->get();

        $this->blade('<x-timeline :history="$history" />', ['history' => $history])
            ->assertSee('Status changed')
            ->assertSee('Brian Otieno')
            ->assertSee('In progress')
            ->assertSee('Escalated')
            ->assertSee('System')
            ->assertSee('is-system', false)
            ->assertSee('Escalated to level 1.');
    }

    public function test_sidebar_lists_only_pages_that_exist_for_the_role(): void
    {
        $this->defineRoutes(['dashboard', 'tickets.index', 'tickets.create', 'admin.users.index']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->blade('<x-sidebar />')
            ->assertSee('Dashboard')
            ->assertSee('My tickets')
            ->assertSee('data-shortcut="new-ticket"', false)
            ->assertDontSee('Users')
            ->assertDontSee('Notifications');
    }

    public function test_sidebar_hides_admin_pages_from_staff_even_when_they_exist(): void
    {
        $this->defineRoutes(['dashboard', 'tickets.index', 'admin.users.index']);

        $this->actingAs(User::factory()->create())->blade('<x-sidebar />')->assertDontSee('Users');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->blade('<x-sidebar />')->assertSee('Users')->assertDontSee('My tickets');
    }

    public function test_the_shortcut_legend_lists_only_shortcuts_that_work_for_the_user(): void
    {
        $this->defineRoutes(['dashboard', 'tickets.index', 'tickets.create', 'admin.users.index']);

        $this->actingAs(User::factory()->create())->blade('<x-sidebar />')
            ->assertSee('New ticket')
            ->assertSee('Dashboard')
            ->assertSee('Search');

        $this->actingAs(User::factory()->admin()->create())->blade('<x-sidebar />')
            ->assertDontSee('New ticket')
            ->assertSee('Search');
    }

    public function test_sidebar_without_a_signed_in_user_is_empty(): void
    {
        $this->blade('<x-sidebar />')->assertDontSee('nav-item-link', false);
    }

    public function test_sidebar_uses_the_configured_organization_not_a_hard_coded_one(): void
    {
        config(['hdtms.organization.name' => 'Acme Support Desk', 'hdtms.organization.logo' => null]);

        $this->blade('<x-sidebar />')->assertSee('Acme Support Desk');
    }

    public function test_topbar_shows_the_unread_count_for_the_signed_in_user_only(): void
    {
        $this->defineRoutes(['notifications.index']);
        $me = User::factory()->create(['name' => 'Wanjiku Kamau']);
        $other = User::factory()->create();
        Notification::factory()->count(2)->for($me)->create();
        Notification::factory()->for($me)->read()->create();
        Notification::factory()->count(5)->for($other)->create();
        Notification::factory()->for($me)->state(['channel' => NotificationChannel::Email])->create();

        $this->actingAs($me)->blade('<x-topbar />')
            ->assertSee('Notifications, 3 unread')
            ->assertSee('WK')
            ->assertSee('Wanjiku Kamau')
            ->assertSee($me->role->label());
    }

    public function test_topbar_caps_a_large_unread_count(): void
    {
        $this->defineRoutes(['notifications.index']);
        $me = User::factory()->create();
        Notification::factory()->count(100)->for($me)->create();

        $this->actingAs($me)->blade('<x-topbar />')->assertSee('99+');
    }

    public function test_topbar_offers_search_and_sign_out_only_when_those_pages_exist(): void
    {
        $user = User::factory()->create();
        $this->defineRoutes([]);

        $this->actingAs($user)->blade('<x-topbar />')
            ->assertDontSee('data-shortcut-search', false)
            ->assertDontSee('Sign out');

        $this->defineRoutes(['tickets.index', 'logout']);

        $this->actingAs($user)->blade('<x-topbar />')
            ->assertSee('data-shortcut-search', false)
            ->assertSee('Sign out');
    }

    public function test_layout_has_a_skip_link_a_main_landmark_and_a_toast_region(): void
    {
        $this->blade('<x-layout.app title="Tickets">Body</x-layout.app>')
            ->assertSee('class="skip-link" href="#main"', false)
            ->assertSee('<main id="main"', false)
            ->assertSee('id="toast-region"', false)
            ->assertSee('<title>Tickets · '.config('app.name').'</title>', false);
    }

    public function test_guest_layout_uses_the_configured_branding(): void
    {
        config(['hdtms.organization.name' => 'Acme Support Desk']);

        $this->blade('<x-layout.guest>Hi</x-layout.guest>')
            ->assertSee('Acme Support Desk')
            ->assertSee('<title>'.config('app.name').'</title>', false);
    }

    public function test_unknown_icons_fail_loudly(): void
    {
        $this->expectException(Throwable::class);

        (string) $this->blade('<x-icon name="does-not-exist" />');
    }

    public function test_user_roles_have_a_preview_for_the_sidebar(): void
    {
        $this->blade('<x-sidebar :role="$role" :preview="true" current="Reports" />', ['role' => UserRole::Admin])
            ->assertSee('Reports')
            ->assertSee('aria-current="page"', false);
    }
}
