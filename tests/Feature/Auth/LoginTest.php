<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TC02: signing in.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const FAILURE = 'Invalid email or password.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_sign_in_page_asks_for_email_and_password(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('remember', false);
    }

    public function test_tc02_a_user_signs_in_with_the_right_email_and_password(): void
    {
        $user = User::factory()->create(['email' => 'wanjiku.kamau@example.test']);

        $this->post('/login', ['email' => 'wanjiku.kamau@example.test', 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_every_role_lands_on_the_dashboard(): void
    {
        foreach ([User::factory()->create(), User::factory()->agent()->create(), User::factory()->admin()->create()] as $user) {
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
            $this->get('/dashboard')->assertOk()->assertSee('Your account')->assertSee($user->role->label());
            $this->post('/logout');
            $this->assertGuest();
        }
    }

    public function test_the_email_is_matched_without_regard_to_capital_letters_or_spaces(): void
    {
        $user = User::factory()->create(['email' => 'wanjiku.kamau@example.test']);

        $this->post('/login', ['email' => '  Wanjiku.Kamau@Example.TEST ', 'password' => 'password'])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_visitor_returns_to_the_page_they_asked_for_after_signing_in(): void
    {
        $user = User::factory()->create();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function failures(): array
    {
        return [
            'wrong password' => ['wanjiku.kamau@example.test', 'not-the-password'],
            'unknown email' => ['nobody@example.test', 'password'],
            'empty password' => ['wanjiku.kamau@example.test', ''],
        ];
    }

    #[DataProvider('failures')]
    public function test_every_failure_shows_the_same_message_and_signs_nobody_in(string $email, string $password): void
    {
        User::factory()->create(['email' => 'wanjiku.kamau@example.test']);

        $response = $this->from('/login')->post('/login', ['email' => $email, 'password' => $password]);

        $response->assertRedirect('/login');
        $this->assertGuest();

        if ($password !== '') {
            $response->assertSessionHasErrors(['email' => self::FAILURE]);
        } else {
            $response->assertSessionHasErrors('password');
        }
    }

    public function test_the_failure_message_is_identical_for_unknown_wrong_deactivated_and_locked_accounts(): void
    {
        User::factory()->create(['email' => 'known@example.test']);
        User::factory()->inactive()->create(['email' => 'inactive@example.test']);
        User::factory()->locked()->create(['email' => 'locked@example.test']);

        $messages = [];
        foreach ([['nobody@example.test', 'password'], ['known@example.test', 'wrong'], ['inactive@example.test', 'password'], ['locked@example.test', 'password']] as [$email, $password]) {
            $this->flushSession();
            $this->from('/login')->post('/login', ['email' => $email, 'password' => $password])->assertRedirect('/login');
            $messages[] = $this->app['session.store']->get('errors')->first('email');
        }

        $this->assertSame([self::FAILURE], array_values(array_unique($messages)));
    }

    public function test_a_failed_sign_in_keeps_the_email_but_never_the_password(): void
    {
        $this->from('/login')->post('/login', ['email' => 'wanjiku.kamau@example.test', 'password' => 'secret-guess']);

        $this->get('/login')->assertSee('value="wanjiku.kamau@example.test"', false)->assertDontSee('secret-guess', false);
    }

    public function test_a_deactivated_user_cannot_sign_in_even_with_the_right_password(): void
    {
        $user = User::factory()->inactive()->create();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => self::FAILURE]);

        $this->assertGuest();
        $this->assertSame(0, $user->fresh()->failed_logins, 'A correct password is not a failed attempt.');
    }

    public function test_the_remember_me_option_is_ignored_even_when_sent_by_hand(): void
    {
        $user = User::factory()->create();
        $tokenBefore = $user->remember_token;

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => '1']);

        $response->assertRedirect('/dashboard');
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertStringStartsNotWith('remember_web', $cookie->getName());
        }
        $this->assertSame($tokenBefore, $user->fresh()->remember_token, 'No remember token may be issued.');
    }

    public function test_sign_in_changes_the_session_id_to_stop_session_fixation(): void
    {
        $user = User::factory()->create();
        $this->startSession();
        $before = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_signing_out_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_the_sign_in_form_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'guess'])->assertStatus(302);
        }

        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'guess'])
            ->assertStatus(429)
            ->assertSee('Too many attempts');
    }

    public function test_one_address_cannot_try_many_emails_without_limit(): void
    {
        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->post('/login', ['email' => "person{$attempt}@example.test", 'password' => 'guess'])->assertStatus(302);
        }

        $this->post('/login', ['email' => 'person31@example.test', 'password' => 'guess'])->assertStatus(429);
    }

    public function test_signed_in_users_are_kept_off_the_sign_in_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect('/dashboard');
    }

    public function test_the_dashboard_needs_a_signed_in_user(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
