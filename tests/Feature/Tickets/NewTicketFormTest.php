<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SlaPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewTicketFormTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([CategorySeeder::class, SlaPolicySeeder::class]);
        $this->me = User::factory()->create();
    }

    public function test_the_form_has_every_field_and_posts_files_securely(): void
    {
        $this->actingAs($this->me)->get('/tickets/create')
            ->assertOk()
            ->assertSee('Report a problem')
            ->assertSee('name="title"', false)
            ->assertSee('name="category_id"', false)
            ->assertSee('name="priority"', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="attachment"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('accept=".jpg,.jpeg,.png,.pdf"', false)
            ->assertSee('maxlength="150"', false)
            ->assertSee('maxlength="5000"', false);
    }

    public function test_every_label_hint_and_field_is_connected_for_assistive_technology(): void
    {
        $this->actingAs($this->me)->get('/tickets/create')
            ->assertSee('for="field-title"', false)
            ->assertSee('for="field-category_id"', false)
            ->assertSee('for="field-description"', false)
            ->assertSee('for="field-attachment"', false)
            ->assertSee('<legend class="form-label">How urgent is it?', false)
            ->assertSee('aria-describedby="field-title-hint"', false);
    }

    public function test_the_category_guide_lists_each_active_category_with_what_it_covers(): void
    {
        $this->actingAs($this->me)->get('/tickets/create')
            ->assertSee('Which category?')
            ->assertSee('Hardware')
            ->assertSee('Computers, laptops, monitors, keyboards and other equipment.')
            ->assertSee('Email and Accounts')
            ->assertSee('Anything that does not fit the categories above.');
    }

    public function test_a_deactivated_category_disappears_from_the_form_but_its_tickets_remain(): void
    {
        $category = Category::query()->where('name', 'Printing')->firstOrFail();
        $ticket = \App\Models\Ticket::factory()->for($this->me, 'requester')->create(['category_id' => $category->id, 'title' => 'Old printing ticket']);
        $category->forceFill(['is_active' => false])->save();

        $this->actingAs($this->me)->get('/tickets/create')->assertDontSee('Printers, scanners and photocopiers.')->assertDontSee('<option value="'.$category->id.'"', false);
        $this->actingAs($this->me)->get('/tickets/'.$ticket->ticket_no)->assertOk()->assertSee('Printing');
    }

    public function test_priority_is_explained_in_plain_words_with_the_time_we_aim_for(): void
    {
        $this->actingAs($this->me)->get('/tickets/create')
            ->assertSee('How urgent is it?')
            ->assertSee('I cannot work, or many people are affected. We aim to resolve it within 4 hours.')
            ->assertSee('It slows my work down, but I can still get things done. We aim to resolve it within 8 hours.')
            ->assertSee('Not urgent. I can keep working while this is looked at. We aim to resolve it within 24 hours.');
    }

    public function test_the_promised_hours_follow_the_sla_settings(): void
    {
        SlaPolicy::query()->where('priority', 'high')->update(['resolution_hours' => 1]);

        $this->actingAs($this->me)->get('/tickets/create')->assertSee('We aim to resolve it within 1 hour.')->assertDontSee('within 1 hours');
    }

    public function test_no_priority_is_preselected_so_the_person_has_to_choose(): void
    {
        $html = $this->actingAs($this->me)->get('/tickets/create')->assertOk()->assertSee('value="low"', false)->assertSee('value="high"', false)->getContent();

        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="priority"[^>]*\\schecked/', (string) $html);
    }

    public function test_the_priority_the_person_chose_stays_chosen_after_a_mistake(): void
    {
        $this->actingAs($this->me)->from('/tickets/create')->post('/tickets', ['title' => 'abc', 'priority' => 'low']);

        $html = $this->actingAs($this->me)->get('/tickets/create')->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*value="low"[^>]*checked/', (string) $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*value="medium"[^>]*checked/', (string) $html);
    }

    public function test_the_page_has_no_inline_script_so_a_strict_content_security_policy_can_apply(): void
    {
        $html = $this->actingAs($this->me)->get('/tickets/create')->getContent();

        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', (string) $html);
        $this->assertStringNotContainsString(' onclick=', (string) $html);
    }

    public function test_the_list_invites_staff_to_report_a_problem(): void
    {
        $this->actingAs($this->me)->get('/tickets')->assertSee('href="'.route('tickets.create').'"', false)->assertSee('Report a problem');
    }

    public function test_staff_get_the_new_ticket_menu_item_and_shortcut(): void
    {
        $this->actingAs($this->me)->get('/tickets')->assertSee('data-shortcut="new-ticket"', false);
    }

    public function test_agents_and_admins_have_the_new_ticket_menu_item_and_shortcut_too(): void
    {
        foreach ([User::factory()->agent()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get('/dashboard')->assertSee('New ticket')->assertSee('data-shortcut="new-ticket"', false);
            $this->actingAs($user)->get('/tickets/create')->assertOk()->assertSee('Report a problem');
        }
    }
}
