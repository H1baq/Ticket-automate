<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\LoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * TC03: five wrong passwords in a row lock the account for fifteen minutes.
 */
class LockoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create(['email' => 'wanjiku.kamau@example.test']);
    }

    private function failSignIn(int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->from('/login')->post('/login', ['email' => 'wanjiku.kamau@example.test', 'password' => 'wrong-'.$i]);
        }
    }

    /**
     * @return TestResponse<Response>
     */
    private function signInWithTheRightPassword(): TestResponse
    {
        return $this->from('/login')->post('/login', ['email' => 'wanjiku.kamau@example.test', 'password' => 'password']);
    }

    public function test_tc03_five_failures_lock_the_account_for_fifteen_minutes(): void
    {
        $this->travelTo(now()->startOfMinute());

        $this->failSignIn(5);

        $user = $this->user->fresh();
        $this->assertSame(5, $user->failed_logins);
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->equalTo(now()->addMinutes(15)));
    }

    public function test_four_failures_do_not_lock_the_account(): void
    {
        $this->failSignIn(4);

        $user = $this->user->fresh();
        $this->assertSame(4, $user->failed_logins);
        $this->assertNull($user->locked_until);
        $this->signInWithTheRightPassword()->assertRedirect('/dashboard');
    }

    public function test_a_locked_account_refuses_even_the_right_password_with_the_usual_message(): void
    {
        $this->failSignIn(5);

        $this->signInWithTheRightPassword()
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Invalid email or password.']);

        $this->assertGuest();
    }

    public function test_the_lock_holds_for_fifteen_minutes_and_then_ends(): void
    {
        $this->travelTo(now()->startOfMinute());
        $this->failSignIn(5);

        $this->travel(14)->minutes();
        $this->signInWithTheRightPassword()->assertRedirect('/login');
        $this->assertGuest();

        $this->travel(61)->seconds();
        $this->signInWithTheRightPassword()->assertRedirect('/dashboard');

        $user = $this->user->fresh();
        $this->assertSame(0, $user->failed_logins);
        $this->assertNull($user->locked_until);
    }

    public function test_attempts_during_the_lock_do_not_extend_it(): void
    {
        $this->travelTo(now()->startOfMinute());
        $this->failSignIn(5);
        $lockedUntil = $this->user->fresh()->locked_until;

        $this->travel(5)->minutes();
        $this->failSignIn(3);

        $this->assertTrue($this->user->fresh()->locked_until->equalTo($lockedUntil));
        $this->assertSame(5, $this->user->fresh()->failed_logins);
    }

    public function test_a_successful_sign_in_resets_the_counter(): void
    {
        $this->failSignIn(3);
        $this->signInWithTheRightPassword()->assertRedirect('/dashboard');
        $this->assertSame(0, $this->user->fresh()->failed_logins);

        $this->post('/logout');
        $this->failSignIn(4);

        $this->assertNull($this->user->fresh()->locked_until, 'Three failures, a success, then four more must not lock.');
    }

    public function test_after_a_lock_ends_the_next_failure_starts_a_fresh_count(): void
    {
        $this->failSignIn(5);
        $this->travel(16)->minutes();

        $this->failSignIn(1);

        $user = $this->user->fresh();
        $this->assertSame(1, $user->failed_logins);
        $this->assertNull($user->locked_until);
    }

    public function test_failures_for_one_account_never_lock_another(): void
    {
        $other = User::factory()->create(['email' => 'other@example.test']);

        $this->failSignIn(5);

        $this->assertNull($other->fresh()->locked_until);
        $this->from('/login')->post('/login', ['email' => 'other@example.test', 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_locking_is_logged_without_the_email_or_password(): void
    {
        $log = Log::spy();

        $this->failSignIn(5);

        $log->shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context): bool => $context === ['user_id' => $this->user->id] && ! str_contains($message, '@'),
        )->once();
    }

    public function test_the_service_reports_a_refusal_the_same_way_for_every_cause(): void
    {
        $service = app(LoginService::class);

        $this->assertNull($service->attempt('nobody@example.test', 'password'));
        $this->assertNull($service->attempt('wanjiku.kamau@example.test', 'wrong'));
        $this->assertInstanceOf(User::class, $service->attempt('wanjiku.kamau@example.test', 'password'));
    }

    public function test_resetting_failures_clears_a_lock(): void
    {
        $this->failSignIn(5);

        app(LoginService::class)->resetFailures($this->user->fresh());

        $user = $this->user->fresh();
        $this->assertSame(0, $user->failed_logins);
        $this->assertNull($user->locked_until);
        $this->signInWithTheRightPassword()->assertRedirect('/dashboard');
    }
}
