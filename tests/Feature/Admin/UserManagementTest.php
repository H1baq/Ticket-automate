<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Events\UserCreated;
use App\Listeners\SendSetPasswordLink;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create(['name' => 'Head of ICT', 'email' => 'head.ict@example.test']);
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertListsInOrder(string $url, array $expected): void
    {
        $this->actingAs($this->admin)->get($url)->assertOk()->assertSeeInOrder($expected);
    }

    // --- list, search, filter, sort -------------------------------------------------------

    public function test_the_list_shows_each_account_with_role_department_and_status(): void
    {
        User::factory()->agent()->create(['name' => 'Brian Otieno', 'email' => 'brian.otieno@example.test', 'department' => 'ICT']);
        User::factory()->inactive()->create(['name' => 'Peter Maina', 'department' => null]);

        $this->actingAs($this->admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('Brian Otieno')
            ->assertSee('brian.otieno@example.test')
            ->assertSee('Support agent')
            ->assertSee('Deactivated')
            ->assertSee('Active')
            ->assertSee('—')
            ->assertSee('(you)');
    }

    public function test_the_list_shows_twenty_users_a_page(): void
    {
        User::factory()->count(45)->create();

        $this->actingAs($this->admin)->get('/admin/users')->assertOk()->assertSee('Showing 1–20 of 46 users');
        $this->actingAs($this->admin)->get('/admin/users?page=3')->assertOk()->assertSee('Showing 41–46 of 46 users');
    }

    public function test_search_finds_a_name_or_an_email_in_any_case(): void
    {
        User::factory()->create(['name' => 'Wanjiku Kamau', 'email' => 'w.k@example.test']);
        User::factory()->create(['name' => 'Kevin Mwangi', 'email' => 'kevin@example.test']);

        $this->actingAs($this->admin)->get('/admin/users?search=WANJI')->assertSee('Wanjiku Kamau')->assertDontSee('Kevin Mwangi');
        $this->actingAs($this->admin)->get('/admin/users?search=KEVIN@')->assertSee('Kevin Mwangi')->assertDontSee('Wanjiku Kamau');
    }

    public function test_search_treats_percent_and_underscore_literally(): void
    {
        User::factory()->create(['name' => '100% Real', 'email' => 'real@example.test']);
        User::factory()->create(['name' => 'Anything Else', 'email' => 'else@example.test']);
        User::factory()->create(['name' => 'Under_score', 'email' => 'u@example.test']);
        User::factory()->create(['name' => 'UnderXscore', 'email' => 'x@example.test']);

        $this->actingAs($this->admin)->get('/admin/users?search=%25')->assertSee('100% Real')->assertDontSee('Anything Else');
        $this->actingAs($this->admin)->get('/admin/users?search=r_s')->assertSee('Under_score')->assertDontSee('UnderXscore');
    }

    public function test_search_cannot_be_used_for_sql_injection(): void
    {
        User::factory()->create(['name' => 'Kevin Mwangi']);

        // If the text were put into the SQL, "OR 1=1" would match everyone. Treated as plain text it matches nobody.
        $this->actingAs($this->admin)->get('/admin/users?search='.urlencode("' OR 1=1 -- "))
            ->assertOk()
            ->assertSee('No users match these filters')
            ->assertDontSee('Kevin Mwangi');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_the_role_and_status_filters_combine(): void
    {
        User::factory()->agent()->create(['name' => 'Active Agent']);
        User::factory()->agent()->inactive()->create(['name' => 'Gone Agent']);
        User::factory()->create(['name' => 'Plain Staff']);

        $this->actingAs($this->admin)->get('/admin/users?role=agent')->assertSee('Active Agent')->assertSee('Gone Agent')->assertDontSee('Plain Staff');
        $this->actingAs($this->admin)->get('/admin/users?status=deactivated')->assertSee('Gone Agent')->assertDontSee('Active Agent');
        $this->actingAs($this->admin)->get('/admin/users?role=agent&status=active')->assertSee('Active Agent')->assertDontSee('Gone Agent')->assertDontSee('Plain Staff');
    }

    public function test_an_empty_result_offers_a_way_back(): void
    {
        $this->actingAs($this->admin)->get('/admin/users?search=zzzz')
            ->assertOk()
            ->assertSee('No users match these filters')
            ->assertSee('Clear filters')
            ->assertDontSee('<table', false);
    }

    public function test_the_list_sorts_by_a_chosen_column_in_both_directions(): void
    {
        User::factory()->create(['name' => 'Aaron Zed', 'department' => 'Library']);
        User::factory()->create(['name' => 'Zoe Abe', 'department' => 'Estates']);

        $this->assertListsInOrder('/admin/users?sort=name&direction=asc', ['Aaron Zed', 'Head of ICT', 'Zoe Abe']);
        $this->assertListsInOrder('/admin/users?sort=name&direction=desc', ['Zoe Abe', 'Head of ICT', 'Aaron Zed']);
        $this->assertListsInOrder('/admin/users?sort=department&direction=asc', ['Estates', 'Library']);
    }

    public function test_the_list_defaults_to_name_order(): void
    {
        User::factory()->create(['name' => 'Zoe Abe']);
        User::factory()->create(['name' => 'Aaron Zed']);

        $this->assertListsInOrder('/admin/users', ['Aaron Zed', 'Head of ICT', 'Zoe Abe']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badQueries(): array
    {
        return [
            'unknown sort column' => ['sort=password'],
            'sql in the sort' => ['sort='.'name;drop table users'],
            'bad direction' => ['direction=sideways'],
            'unknown role' => ['role=superuser'],
            'unknown status' => ['status=maybe'],
            'search too long' => ['search='.'a'],
        ];
    }

    #[DataProvider('badQueries')]
    public function test_unexpected_list_parameters_are_refused_not_trusted(string $query): void
    {
        if ($query === 'search=a') {
            $query = 'search='.str_repeat('a', 101);
        }

        $this->actingAs($this->admin)->from('/admin/users')->get('/admin/users?'.$query)->assertRedirect('/admin/users');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_filters_and_sort_stay_in_the_page_links(): void
    {
        User::factory()->count(30)->create(['department' => 'Finance']);

        $html = $this->actingAs($this->admin)->get('/admin/users?search=&role=user&sort=email&direction=desc')->assertOk()->getContent();

        $this->assertStringContainsString('role=user', $html);
        $this->assertStringContainsString('sort=email', $html);
        $this->assertMatchesRegularExpression('/page=2[^"]*"/', $html);
    }

    // --- create ---------------------------------------------------------------------------

    public function test_the_new_user_form_offers_the_three_roles(): void
    {
        $this->actingAs($this->admin)->get('/admin/users/create')
            ->assertOk()
            ->assertSee('Staff user')
            ->assertSee('Support agent')
            ->assertSee('Administrator')
            ->assertSee('We email this address a link to set a password');
    }

    public function test_creating_a_user_stores_the_account_and_queues_the_set_password_email(): void
    {
        Event::fake([UserCreated::class]);

        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Brian Otieno',
            'email' => '  Brian.Otieno@Example.TEST ',
            'role' => 'agent',
            'department' => 'ICT',
        ])->assertRedirect('/admin/users')->assertSessionHas('toast.type', 'success');

        $user = User::query()->where('email', 'brian.otieno@example.test')->sole();
        $this->assertSame(UserRole::Agent, $user->role);
        $this->assertSame('ICT', $user->department);
        $this->assertTrue($user->is_active);
        Event::assertDispatched(UserCreated::class, fn (UserCreated $event): bool => $event->user->is($user));
    }

    public function test_nobody_knows_the_new_users_first_password(): void
    {
        Event::fake([UserCreated::class]);

        $this->actingAs($this->admin)->post('/admin/users', ['name' => 'Brian Otieno', 'email' => 'b@example.test', 'role' => 'user']);

        $user = User::query()->where('email', 'b@example.test')->sole();
        foreach (['password', 'Password1', '', 'b@example.test', 'Brian Otieno'] as $guess) {
            $this->assertFalse(Hash::check($guess, $user->password));
        }
    }

    public function test_the_new_user_is_emailed_a_link_that_really_sets_a_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'new.person@example.test']);

        (new SendSetPasswordLink())->handle(new UserCreated($user));

        Notification::assertSentTo($user, SetPasswordNotification::class, function (SetPasswordNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return str_contains((string) $mail->actionUrl, '/reset-password/'.$notification->token)
                && str_contains((string) $mail->actionUrl, urlencode('new.person@example.test'))
                && str_contains($mail->subject, config('app.name'))
                && Password::broker()->tokenExists($user, $notification->token);
        });
    }

    public function test_the_email_tells_the_person_what_to_do_if_the_link_expires(): void
    {
        $text = collect((new SetPasswordNotification('abc'))->toMail(User::factory()->make(['name' => 'Brian Otieno']))->outroLines)->implode(' ');

        $this->assertStringContainsString('60 minutes', $text);
        $this->assertStringContainsString('Forgot your password?', $text);
    }

    public function test_the_listener_is_queued_so_a_slow_mail_server_never_slows_the_form(): void
    {
        $this->assertContains(\Illuminate\Contracts\Queue\ShouldQueue::class, class_implements(SendSetPasswordLink::class));
    }

    public function test_creating_through_the_form_sends_the_email_to_the_new_user(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->post('/admin/users', ['name' => 'Brian Otieno', 'email' => 'b@example.test', 'role' => 'user'])->assertRedirect();

        Notification::assertSentTo(User::query()->where('email', 'b@example.test')->sole(), SetPasswordNotification::class);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidNewUsers(): array
    {
        return [
            'no name' => [['name' => ''], 'name'],
            'name too long' => [['name' => str_repeat('a', 256)], 'name'],
            'no email' => [['email' => ''], 'email'],
            'bad email' => [['email' => 'nope'], 'email'],
            'duplicate email' => [['email' => 'head.ict@example.test'], 'email'],
            'duplicate email in capitals' => [['email' => 'HEAD.ICT@EXAMPLE.TEST'], 'email'],
            'no role' => [['role' => ''], 'role'],
            'unknown role' => [['role' => 'superuser'], 'role'],
            'department too long' => [['department' => str_repeat('a', 101)], 'department'],
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     */
    #[DataProvider('invalidNewUsers')]
    public function test_invalid_new_users_are_refused_and_nothing_is_created(array $overrides, string $field): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->from('/admin/users/create')
            ->post('/admin/users', ['name' => 'Brian Otieno', 'email' => 'b@example.test', 'role' => 'user', ...$overrides])
            ->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('users', 1);
        Notification::assertNothingSent();
    }

    public function test_a_new_user_cannot_be_created_already_locked_or_inactive(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Brian Otieno', 'email' => 'b@example.test', 'role' => 'user',
            'is_active' => '0', 'failed_logins' => '9', 'locked_until' => '2099-01-01', 'password' => 'chosen-by-admin',
        ]);

        $user = User::query()->where('email', 'b@example.test')->sole();
        $this->assertTrue($user->is_active);
        $this->assertSame(0, $user->failed_logins);
        $this->assertFalse(Hash::check('chosen-by-admin', $user->password));
    }

    // --- edit -----------------------------------------------------------------------------

    public function test_an_administrator_edits_name_department_and_role_of_someone_else(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'department' => 'Library']);

        $this->actingAs($this->admin)->put("/admin/users/{$user->id}", ['name' => 'New Name', 'department' => 'Finance', 'role' => 'agent'])
            ->assertRedirect('/admin/users')
            ->assertSessionHas('toast.message', 'Changes to New Name saved.');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('Finance', $user->department);
        $this->assertSame(UserRole::Agent, $user->role);
    }

    public function test_the_email_cannot_be_changed_from_the_edit_form(): void
    {
        $user = User::factory()->create(['email' => 'original@example.test']);

        $this->actingAs($this->admin)->put("/admin/users/{$user->id}", ['name' => 'Same', 'email' => 'changed@example.test', 'role' => 'user']);

        $this->assertSame('original@example.test', $user->fresh()->email);
    }

    public function test_the_department_can_be_cleared(): void
    {
        $user = User::factory()->create(['department' => 'Library']);

        $this->actingAs($this->admin)->put("/admin/users/{$user->id}", ['name' => 'Same', 'department' => '', 'role' => 'user']);

        $this->assertNull($user->fresh()->department);
    }

    public function test_an_administrator_cannot_change_their_own_role(): void
    {
        $this->actingAs($this->admin)->from("/admin/users/{$this->admin->id}/edit")
            ->put("/admin/users/{$this->admin->id}", ['name' => 'Head of ICT', 'role' => 'user'])
            ->assertRedirect("/admin/users/{$this->admin->id}/edit")
            ->assertSessionHasErrors(['role' => 'You cannot change your own role.']);

        $this->assertSame(UserRole::Admin, $this->admin->fresh()->role);
    }

    public function test_an_administrator_can_still_edit_their_own_name_and_department(): void
    {
        $this->actingAs($this->admin)->put("/admin/users/{$this->admin->id}", ['name' => 'Chief of ICT', 'department' => 'Executive', 'role' => 'admin'])
            ->assertRedirect('/admin/users');

        $this->admin->refresh();
        $this->assertSame('Chief of ICT', $this->admin->name);
        $this->assertSame(UserRole::Admin, $this->admin->role);
    }

    public function test_the_edit_form_for_yourself_shows_the_role_as_text_not_a_choice(): void
    {
        $this->actingAs($this->admin)->get("/admin/users/{$this->admin->id}/edit")
            ->assertOk()
            ->assertSee('You cannot change your own role.')
            ->assertDontSee('name="role"', false);

        $other = User::factory()->create();
        $this->actingAs($this->admin)->get("/admin/users/{$other->id}/edit")->assertOk()->assertSee('name="role"', false);
    }

    public function test_an_edit_with_an_invalid_role_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->from("/admin/users/{$user->id}/edit")
            ->put("/admin/users/{$user->id}", ['name' => 'Same', 'role' => 'superuser'])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::User, $user->fresh()->role);
    }

    public function test_an_edit_without_a_role_keeps_the_role(): void
    {
        $user = User::factory()->agent()->create();

        $this->actingAs($this->admin)->put("/admin/users/{$user->id}", ['name' => 'Renamed'])->assertRedirect('/admin/users');

        $this->assertSame(UserRole::Agent, $user->fresh()->role);
    }

    public function test_an_edit_needs_a_name(): void
    {
        $user = User::factory()->create(['name' => 'Keep Me']);

        $this->actingAs($this->admin)->from("/admin/users/{$user->id}/edit")->put("/admin/users/{$user->id}", ['name' => '', 'role' => 'user'])->assertSessionHasErrors('name');

        $this->assertSame('Keep Me', $user->fresh()->name);
    }

    // --- deactivate and reactivate --------------------------------------------------------

    public function test_deactivating_a_user_signs_them_out_everywhere_and_keeps_their_record(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'their-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
        DB::table('sessions')->insert(['id' => 'someone-else', 'user_id' => $this->admin->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);

        $this->actingAs($this->admin)->from('/admin/users')->patch("/admin/users/{$user->id}/deactivate")
            ->assertRedirect('/admin/users')
            ->assertSessionHas('toast.type', 'success');

        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => 'their-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'someone-else']);
        $this->assertModelExists($user);
    }

    public function test_a_deactivated_user_cannot_sign_in_until_reactivated(): void
    {
        $user = User::factory()->create(['email' => 'wanjiku@example.test']);
        $this->actingAs($this->admin)->patch("/admin/users/{$user->id}/deactivate");
        $this->post('/logout');

        $this->from('/login')->post('/login', ['email' => 'wanjiku@example.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($this->admin)->patch("/admin/users/{$user->id}/reactivate")->assertSessionHas('toast.message', "{$user->name} can sign in again.");
        $this->post('/logout');

        $this->post('/login', ['email' => 'wanjiku@example.test', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_administrator_cannot_deactivate_themselves(): void
    {
        $this->actingAs($this->admin)->patch("/admin/users/{$this->admin->id}/deactivate")->assertForbidden();

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_deactivating_twice_or_reactivating_an_active_user_is_refused(): void
    {
        $active = User::factory()->create();
        $inactive = User::factory()->inactive()->create();

        $this->actingAs($this->admin)->patch("/admin/users/{$inactive->id}/deactivate")->assertForbidden();
        $this->actingAs($this->admin)->patch("/admin/users/{$active->id}/reactivate")->assertForbidden();
    }

    public function test_the_list_offers_the_right_action_for_each_state_and_none_for_yourself(): void
    {
        $active = User::factory()->create(['name' => 'Active Person']);
        $inactive = User::factory()->inactive()->create(['name' => 'Inactive Person']);

        $html = $this->actingAs($this->admin)->get('/admin/users')->assertOk()->getContent();

        $this->assertStringContainsString("/admin/users/{$active->id}/deactivate", $html);
        $this->assertStringContainsString("/admin/users/{$inactive->id}/reactivate", $html);
        $this->assertStringNotContainsString("/admin/users/{$active->id}/reactivate", $html);
        $this->assertStringNotContainsString("/admin/users/{$inactive->id}/deactivate", $html);
        $this->assertStringNotContainsString("/admin/users/{$this->admin->id}/deactivate", $html);
    }

    public function test_the_deactivate_confirmation_explains_what_happens(): void
    {
        $user = User::factory()->create(['name' => 'Wanjiku Kamau']);

        $this->actingAs($this->admin)->get('/admin/users')
            ->assertSee('Deactivate Wanjiku Kamau? They will be signed out and cannot sign in until you reactivate them. Their tickets stay in the system.')
            ->assertSee('id="confirm-deactivate"', false)
            ->assertSee('name="_method" value="PATCH"', false);
    }

    public function test_user_names_are_escaped_on_the_list(): void
    {
        User::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->actingAs($this->admin)->get('/admin/users')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
