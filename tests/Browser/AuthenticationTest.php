<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Journeys through the real sign-in and user administration screens. They run against the
 * development database, so every account they create has a unique email and is removed afterwards.
 */
class AuthenticationTest extends DuskTestCase
{
    /**
     * @var list<string>
     */
    private array $emails = [];

    protected function tearDown(): void
    {
        if ($this->emails !== []) {
            User::query()->whereIn('email', $this->emails)->delete();
            $this->emails = [];
        }

        parent::tearDown();
    }

    private function email(string $label): string
    {
        return $this->emails[] = "dusk.{$label}.".Str::lower(Str::random(8)).'@example.test';
    }

    public function test_a_staff_user_signs_in_sees_the_dashboard_and_signs_out(): void
    {
        $email = $this->email('staff');
        User::factory()->create(['name' => 'Wanjiku Dusk', 'email' => $email]);

        $this->browse(function (Browser $browser) use ($email): void {
            $browser->visit('/')
                ->assertPathIs('/login')
                ->assertSee('Sign in')
                ->screenshot('sign-in')
                ->type('email', $email)
                ->type('password', 'password')
                ->press('Sign in')
                ->waitForLocation('/dashboard')
                ->assertSee('Your account')
                ->assertSee('Wanjiku Dusk')
                ->assertDontSee('Users')
                ->click('[aria-label^="Account menu"]')
                ->waitFor('.dropdown-menu.show')
                ->press('Sign out')
                ->waitForLocation('/login')
                ->assertSee('Sign in');

            $browser->visit('/dashboard')->assertPathIs('/login');
        });
    }

    public function test_a_wrong_password_shows_one_plain_message_and_keeps_the_email(): void
    {
        $email = $this->email('wrong');
        User::factory()->create(['email' => $email]);

        $this->browse(function (Browser $browser) use ($email): void {
            $browser->visit('/login')
                ->type('email', $email)
                ->type('password', 'not-the-password')
                ->press('Sign in')
                ->waitForText('Invalid email or password.')
                ->assertPathIs('/login')
                ->assertInputValue('email', $email)
                ->assertInputValue('password', '')
                ->screenshot('sign-in-error');
        });
    }

    public function test_the_keyboard_shortcut_g_then_d_goes_to_the_dashboard(): void
    {
        $admin = User::factory()->admin()->create(['email' => $this->email('shortcut')]);

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit('/admin/users')
                ->assertPathIs('/admin/users')
                ->keys('', 'g', 'd')
                ->waitForLocation('/dashboard')
                ->assertSee('Your account');
        });
    }

    public function test_an_administrator_creates_edits_and_deactivates_a_user(): void
    {
        $adminEmail = $this->email('admin');
        $newEmail = $this->email('new');
        $admin = User::factory()->admin()->create(['name' => 'Admin Dusk', 'email' => $adminEmail]);

        $this->browse(function (Browser $browser) use ($admin, $newEmail): void {
            $browser->loginAs($admin)
                ->visit('/dashboard')
                ->clickLink('Users')
                ->waitForLocation('/admin/users')
                ->assertSee('New user')
                ->screenshot('admin-users')
                ->clickLink('New user')
                ->waitForLocation('/admin/users/create')
                ->screenshot('admin-users-create')
                ->type('name', 'Brian Dusk')
                ->type('email', $newEmail)
                ->select('role', 'agent')
                ->type('department', 'ICT')
                ->press('Create user')
                ->waitForLocation('/admin/users')
                ->waitFor('.toast-success')
                ->assertSeeIn('.toast-success', 'User Brian Dusk created.')
                ->visit('/admin/users?search='.urlencode($newEmail))
                ->assertSee('Brian Dusk')
                ->assertSee('Support agent')
                ->assertSee('Active');

            $browser->clickLink('Edit')
                ->waitForText('Account details')
                ->assertSee($newEmail)
                ->type('department', 'Network operations')
                ->press('Save changes')
                ->waitFor('.toast-success')
                ->assertSeeIn('.toast-success', 'Changes to Brian Dusk saved.');

            $browser->visit('/admin/users?search='.urlencode($newEmail))
                ->assertSee('Network operations')
                ->click('[data-bs-target="#confirm-deactivate"]')
                ->waitFor('#confirm-deactivate.show')
                ->assertSeeIn('#confirm-deactivate', 'Deactivate Brian Dusk?')
                ->screenshot('admin-users-confirm')
                ->within('#confirm-deactivate', fn (Browser $modal) => $modal->press('Deactivate'))
                ->waitFor('.toast-success')
                ->assertSeeIn('.toast-success', 'Brian Dusk is deactivated')
                ->assertSee('Deactivated');
        });

        $this->assertFalse(User::query()->where('email', $newEmail)->sole()->is_active);
    }

    public function test_the_sign_in_and_user_screens_fit_a_360px_phone(): void
    {
        $admin = User::factory()->admin()->create(['email' => $this->email('phone')]);

        $this->browse(function (Browser $browser) use ($admin): void {
            $this->emulatePhone($browser);

            foreach (['/login', '/forgot-password', '/reset-password/abc'] as $path) {
                $browser->visit($path);
                $this->assertTrue($browser->script('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;')[0], "{$path} scrolls sideways on a phone");
            }

            $browser->loginAs($admin);
            foreach (['/dashboard', '/admin/users', '/admin/users/create', "/admin/users/{$admin->id}/edit"] as $path) {
                $browser->visit($path);
                $this->assertTrue($browser->script('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;')[0], "{$path} scrolls sideways on a phone");
            }

            $browser->visit('/admin/users')->screenshot('admin-users-phone');
        });
    }

    public function test_a_staff_user_who_types_an_admin_address_gets_the_friendly_403_page(): void
    {
        $user = User::factory()->create(['email' => $this->email('curious')]);

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/admin/users')
                ->assertSee('Error 403')
                ->assertSee("You don't have access to this page")
                ->screenshot('forbidden');
        });
    }
}
