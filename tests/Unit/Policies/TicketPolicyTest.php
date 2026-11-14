<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\TicketPolicy;
use PHPUnit\Framework\TestCase;

class TicketPolicyTest extends TestCase
{
    private function user(int $id, UserRole $role, bool $active = true): User
    {
        $user = new User();
        $user->id = $id;
        $user->role = $role;
        $user->is_active = $active;

        return $user;
    }

    private function ticket(int $requesterId, ?int $agentId = null): Ticket
    {
        $ticket = new Ticket();
        $ticket->requester_id = $requesterId;
        $ticket->agent_id = $agentId;

        return $ticket;
    }

    public function test_the_requester_may_view_their_own_ticket(): void
    {
        $this->assertTrue((new TicketPolicy())->view($this->user(1, UserRole::User), $this->ticket(1)));
    }

    public function test_another_staff_user_may_not_view_it(): void
    {
        $this->assertFalse((new TicketPolicy())->view($this->user(2, UserRole::User), $this->ticket(1)));
    }

    public function test_the_assigned_agent_may_view_it_and_other_agents_may_not(): void
    {
        $policy = new TicketPolicy();
        $ticket = $this->ticket(requesterId: 1, agentId: 5);

        $this->assertTrue($policy->view($this->user(5, UserRole::Agent), $ticket));
        $this->assertFalse($policy->view($this->user(6, UserRole::Agent), $ticket));
    }

    public function test_an_agent_cannot_view_an_unassigned_ticket(): void
    {
        $this->assertFalse((new TicketPolicy())->view($this->user(5, UserRole::Agent), $this->ticket(1, null)));
    }

    public function test_an_agent_cannot_view_a_ticket_just_because_they_reported_one_as_staff(): void
    {
        $this->assertFalse((new TicketPolicy())->view($this->user(1, UserRole::Agent), $this->ticket(requesterId: 1, agentId: 5)));
    }

    public function test_administrators_may_view_every_ticket(): void
    {
        $policy = new TicketPolicy();

        $this->assertTrue($policy->view($this->user(9, UserRole::Admin), $this->ticket(1)));
        $this->assertTrue($policy->view($this->user(9, UserRole::Admin), $this->ticket(1, 5)));
    }

    public function test_a_deactivated_account_may_view_nothing_not_even_its_own_ticket(): void
    {
        $policy = new TicketPolicy();

        $this->assertFalse($policy->view($this->user(1, UserRole::User, false), $this->ticket(1)));
        $this->assertFalse($policy->view($this->user(5, UserRole::Agent, false), $this->ticket(1, 5)));
        $this->assertFalse($policy->view($this->user(9, UserRole::Admin, false), $this->ticket(1)));
    }

    public function test_only_active_staff_users_may_submit_tickets(): void
    {
        $policy = new TicketPolicy();

        $this->assertTrue($policy->create($this->user(1, UserRole::User)));
        $this->assertFalse($policy->create($this->user(1, UserRole::User, false)));
        $this->assertFalse($policy->create($this->user(5, UserRole::Agent)));
        $this->assertFalse($policy->create($this->user(9, UserRole::Admin)));
    }

    public function test_tickets_are_addressed_by_their_number(): void
    {
        $this->assertSame('ticket_no', (new Ticket())->getRouteKeyName());
    }
}
