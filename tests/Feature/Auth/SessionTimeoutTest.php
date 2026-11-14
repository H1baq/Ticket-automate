<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Sessions end after 30 idle minutes. Each request counts as activity.
 */
class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // The test suite normally keeps sessions in memory; the timeout is a property of the real store.
        config(['session.driver' => 'database']);
    }

    private function signIn(): string
    {
        $user = User::factory()->create();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $response->assertRedirect('/dashboard');

        $cookie = $response->getCookie(config('session.cookie'), false);
        $this->assertNotNull($cookie, 'The sign-in response must set the session cookie.');

        return $cookie->getValue();
    }

    /**
     * @return TestResponse<Response>
     */
    private function visitDashboardWith(string $sessionCookie): TestResponse
    {
        // The test application is reused between requests. Forget the guard, the session driver and the
        // container's cached session store, or the session saved in the database is never consulted
        // and the timeout would not be tested at all.
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');

        return $this->withUnencryptedCookie(config('session.cookie'), $sessionCookie)->get('/dashboard');
    }

    public function test_the_session_lifetime_is_thirty_minutes(): void
    {
        $this->assertSame(30, config('session.lifetime'));
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertFalse(config('session.expire_on_close'));
    }

    public function test_a_session_still_works_within_thirty_minutes(): void
    {
        $cookie = $this->signIn();

        $this->travel(29)->minutes();

        $this->visitDashboardWith($cookie)->assertOk();
    }

    public function test_a_session_idle_for_more_than_thirty_minutes_ends(): void
    {
        $cookie = $this->signIn();

        $this->travel(31)->minutes();

        $this->visitDashboardWith($cookie)->assertRedirect('/login');
    }

    public function test_activity_keeps_a_session_alive(): void
    {
        $cookie = $this->signIn();

        $this->travel(25)->minutes();
        $this->visitDashboardWith($cookie)->assertOk();

        $this->travel(25)->minutes();
        $this->visitDashboardWith($cookie)->assertOk();
    }

    public function test_a_deactivated_users_session_is_refused_on_the_very_next_request(): void
    {
        $user = User::factory()->create();
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $cookie = $response->getCookie(config('session.cookie'), false)->getValue();

        $user->is_active = false;
        $user->save();

        $this->visitDashboardWith($cookie)->assertRedirect('/login');
    }
}
