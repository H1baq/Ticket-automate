<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const NEUTRAL = 'If an account exists for that email address, we have sent a link to reset the password. It works for 60 minutes.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, string>
     */
    private function newPassword(string $token, string $email, string $password = 'a-brand-new-password'): array
    {
        return ['token' => $token, 'email' => $email, 'password' => $password, 'password_confirmation' => $password];
    }

    public function test_the_forgot_password_page_asks_only_for_an_email(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('Reset your password')->assertSee('name="email"', false)->assertDontSee('name="password"', false);
        $this->get('/login')->assertSee('Forgot your password?');
    }

    public function test_a_link_is_emailed_to_an_active_account(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'wanjiku.kamau@example.test']);

        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'Wanjiku.Kamau@Example.test'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status', self::NEUTRAL);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_the_answer_is_the_same_for_unknown_and_deactivated_accounts_and_nothing_is_sent(): void
    {
        Notification::fake();
        User::factory()->inactive()->create(['email' => 'inactive@example.test']);

        foreach (['nobody@example.test', 'inactive@example.test'] as $email) {
            $this->from('/forgot-password')->post('/forgot-password', ['email' => $email])
                ->assertRedirect('/forgot-password')
                ->assertSessionHas('status', self::NEUTRAL)
                ->assertSessionHasNoErrors();
        }

        Notification::assertNothingSent();
    }

    public function test_the_email_must_look_like_an_email(): void
    {
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    }

    public function test_the_link_in_the_email_points_at_the_reset_page_with_the_token_and_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return str_contains($url, '/reset-password/'.$notification->token) && str_contains($url, urlencode($user->email));
        });
    }

    public function test_reset_requests_are_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/forgot-password', ['email' => "person{$attempt}@example.test"])->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'person6@example.test'])->assertStatus(429);
    }

    public function test_the_reset_page_carries_the_token_and_email(): void
    {
        $this->get('/reset-password/abc123?email=wanjiku.kamau@example.test')
            ->assertOk()
            ->assertSee('value="abc123"', false)
            ->assertSee('value="wanjiku.kamau@example.test"', false);
    }

    public function test_a_valid_link_sets_the_new_password_and_the_old_one_stops_working(): void
    {
        $user = User::factory()->create(['email' => 'wanjiku.kamau@example.test']);
        $token = Password::createToken($user);

        $this->post('/reset-password', $this->newPassword($token, $user->email))
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'Your password has been reset.');

        $this->assertTrue(Hash::check('a-brand-new-password', $user->fresh()->password));
        $this->post('/login', ['email' => $user->email, 'password' => 'a-brand-new-password'])->assertRedirect('/dashboard');
    }

    public function test_the_old_password_no_longer_signs_in(): void
    {
        $user = User::factory()->create();
        $this->post('/reset-password', $this->newPassword(Password::createToken($user), $user->email));

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_the_link_works_for_sixty_minutes_and_not_longer(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->travel(61)->minutes();
        $this->from('/reset-password/'.$token)->post('/reset-password', $this->newPassword($token, $user->email))
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_the_link_still_works_just_before_it_expires(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->travel(59)->minutes();
        $this->post('/reset-password', $this->newPassword($token, $user->email))->assertRedirect('/login');
    }

    public function test_a_link_can_be_used_only_once(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', $this->newPassword($token, $user->email))->assertRedirect('/login');
        $this->from('/reset-password/'.$token)->post('/reset-password', $this->newPassword($token, $user->email, 'another-password-1'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('a-brand-new-password', $user->fresh()->password));
    }

    public function test_a_link_does_not_work_for_a_different_email(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->from('/reset-password/x')->post('/reset-password', $this->newPassword(Password::createToken($user), $other->email))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $other->fresh()->password));
    }

    public function test_the_new_password_must_meet_the_policy(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->from('/reset-password/'.$token)->post('/reset-password', $this->newPassword($token, $user->email, 'short12'))
            ->assertSessionHasErrors('password');
        $this->from('/reset-password/'.$token)->post('/reset-password', [...$this->newPassword($token, $user->email), 'password_confirmation' => 'different-one'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_resetting_the_password_clears_a_lockout(): void
    {
        $user = User::factory()->locked()->create();

        $this->post('/reset-password', $this->newPassword(Password::createToken($user), $user->email));

        $user = $user->fresh();
        $this->assertSame(0, $user->failed_logins);
        $this->assertNull($user->locked_until);
        $this->post('/login', ['email' => $user->email, 'password' => 'a-brand-new-password'])->assertRedirect('/dashboard');
    }

    public function test_resetting_the_password_ends_the_sessions_that_were_open(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
        DB::table('sessions')->insert(['id' => 'someone-else', 'user_id' => User::factory()->create()->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
        $tokenBefore = $user->remember_token;

        $this->post('/reset-password', $this->newPassword(Password::createToken($user), $user->email));

        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'someone-else']);
        $this->assertNotSame($tokenBefore, $user->fresh()->remember_token);
    }

    public function test_reset_pages_are_for_guests_only(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/forgot-password')->assertRedirect('/dashboard');
        $this->get('/reset-password/abc')->assertRedirect('/dashboard');
    }
}
