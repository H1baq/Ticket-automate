<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\Response;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_default_to_the_user_role_and_are_active(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame(0, $user->failed_logins);
        $this->assertNull($user->locked_until);
    }

    public function test_factory_states_set_role_and_status(): void
    {
        $this->assertSame(UserRole::Agent, User::factory()->agent()->make()->role);
        $this->assertSame(UserRole::Admin, User::factory()->admin()->make()->role);
        $this->assertFalse(User::factory()->inactive()->make()->is_active);
        $this->assertTrue(User::factory()->locked()->make()->locked_until->isFuture());
    }

    public function test_passwords_are_hashed_and_hidden(): void
    {
        $user = User::factory()->create(['password' => 'a-long-password']);

        $this->assertNotSame('a-long-password', $user->password);
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    public function test_role_and_lockout_fields_cannot_be_mass_assigned(): void
    {
        $user = User::query()->create([
            'name' => 'Intruder',
            'email' => 'intruder@example.test',
            'password' => 'a-long-password',
            'role' => 'admin',
            'is_active' => false,
            'failed_logins' => 9,
        ])->fresh();

        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame(0, $user->failed_logins);
    }

    public function test_relations_return_the_users_own_records(): void
    {
        $user = User::factory()->create();
        $agent = User::factory()->agent()->create();
        $requested = Ticket::factory()->for($user, 'requester')->create();
        $assigned = Ticket::factory()->assigned($agent)->create();
        $response = Response::factory()->for($requested)->for($user)->create();
        $notification = Notification::factory()->for($user)->for($requested)->create();

        $user = User::query()
            ->with(['requestedTickets', 'assignedTickets', 'responses', 'notifications'])
            ->findOrFail($user->id);
        $agent = User::query()->with('assignedTickets')->findOrFail($agent->id);

        $this->assertTrue($user->requestedTickets->contains($requested));
        $this->assertTrue($user->assignedTickets->isEmpty());
        $this->assertTrue($user->responses->contains($response));
        $this->assertTrue($user->notifications->contains($notification));
        $this->assertTrue($agent->assignedTickets->contains($assigned));
    }
}
