<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NotificationChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Response;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Realistic demonstration data for development and design review.
 * It never runs in production. Every demo account has the password "password".
 */
class DemoSeeder extends Seeder
{
    private const TICKETS_PER_STATUS = [
        'open' => 18,
        'assigned' => 22,
        'in_progress' => 30,
        'resolved' => 30,
        'closed' => 50,
    ];

    private const AGENTS = [
        'Brian Otieno',
        'Faith Wanjiru',
        'Hassan Abdi',
        'Grace Chebet',
        'Peter Kiprotich',
    ];

    private const STAFF = [
        ['Wanjiku Kamau', 'Finance'],
        ['Kevin Mwangi', 'Finance'],
        ['Achieng Odhiambo', 'Human Resources'],
        ['Samuel Kariuki', 'Human Resources'],
        ['Mercy Njeri', 'Registry'],
        ['Daniel Kipchirchir', 'Registry'],
        ['Halima Mohamed', 'Procurement'],
        ['James Mutua', 'Procurement'],
        ['Esther Wambui', 'Library'],
        ['Collins Ouma', 'Library'],
        ['Naomi Chepkoech', 'Training Programmes'],
        ['Joseph Githinji', 'Training Programmes'],
        ['Lucy Akinyi', 'Training Programmes'],
        ['Abdi Warsame', 'Hospitality'],
        ['Rose Nyambura', 'Hospitality'],
        ['Peter Maina', 'Administration'],
        ['Irene Jebet', 'Administration'],
        ['Martin Omondi', 'Research'],
        ['Susan Muthoni', 'Research'],
        ['Felix Kimani', 'Legal Services'],
        ['Beatrice Atieno', 'Legal Services'],
        ['Caleb Barasa', 'Transport'],
        ['Zipporah Wairimu', 'Estates'],
        ['Isaac Rotich', 'Estates'],
    ];

    /**
     * Ticket text per category name: [title, description].
     *
     * @var array<string, list<array{string, string}>>
     */
    private const TICKET_TEXT = [
        'Hardware' => [
            ['Desktop computer will not switch on', 'The desktop at my desk shows no lights when I press the power button. I checked the cable and the socket and both are fine.'],
            ['Laptop battery drains within 30 minutes', 'My laptop was fine last month. Now it shuts down about half an hour after I unplug the charger.'],
            ['Monitor flickers and goes black', 'The second monitor goes black every few minutes and comes back after I move the cable.'],
            ['Keyboard keys not responding', 'Several keys on my keyboard have stopped working, including the space bar and the letter E.'],
        ],
        'Software' => [
            ['Cannot open the finance system after update', 'Since this morning the finance system shows a blank page after I log in. Other colleagues in the office have the same problem.'],
            ['Need Microsoft Office reinstalled', 'Word and Excel ask me to activate the product every time I open them.'],
            ['PDF reader keeps crashing', 'The PDF reader closes by itself when I open a document larger than 5 MB.'],
            ['Request to install statistics software', 'I need the statistics package installed on my computer for the training programme starting next week.'],
        ],
        'Network' => [
            ['Wi-Fi keeps disconnecting in the training room', 'The Wi-Fi drops every ten minutes during sessions. Participants have to reconnect each time.'],
            ['No internet on the second floor', 'None of the computers in our office can reach the internet since 8 am. The network cable is connected.'],
            ['Network point in room 12 is dead', 'The wall network point in room 12 does not give a connection. I tried two different cables.'],
            ['Cannot connect to VPN from home', 'The VPN client says the connection timed out. It worked on Friday.'],
        ],
        'Email and Accounts' => [
            ['Forgot my password and locked out', 'I entered the wrong password several times and now my account is locked. I need access to submit a report today.'],
            ['Mailbox is full and I cannot receive email', 'I get a message that my mailbox is full. I need more space or help to clear old messages.'],
            ['New staff member needs an email account', 'A new colleague joined our department on Monday and does not have an email account yet.'],
            ['Shared mailbox no longer visible', 'The shared mailbox for our unit disappeared from my Outlook after the weekend.'],
        ],
        'Printing' => [
            ['Printer shows paper jam but there is no paper', 'The printer by the registry counter says paper jam. I opened every tray and cleared it, but the message stays.'],
            ['Scanner does not send to email', 'When I scan a document, the machine says sending failed. It worked last week.'],
            ['Printed pages come out with black lines', 'Every page printed from the third floor printer has a black line down the right side.'],
            ['Cannot find the new printer on my computer', 'The new printer in the library is not listed when I try to print. Other people can see it.'],
        ],
        'Other' => [
            ['Projector in the boardroom has no picture', 'The projector turns on but shows no signal when I connect my laptop. We have a meeting tomorrow morning.'],
            ['Request for a second monitor', 'My work involves comparing two documents all day. A second monitor would help a lot.'],
            ['Telephone extension not ringing', 'Callers say my extension rings out, but my phone does not ring.'],
            ['Need help setting up an online meeting room', 'I would like help to set up a recurring online meeting for our weekly team meeting.'],
        ],
    ];

    private const FIRST_REPLIES = [
        'Thank you for the details. I am looking into this now and will update you shortly.',
        'I have seen the problem and will come to your office this afternoon.',
        'I could reproduce this on my side. I am working on a fix.',
        'Could you tell me if this started after any recent update or change?',
    ];

    private const REQUESTER_REPLIES = [
        'It started yesterday afternoon. Nothing was changed on my side.',
        'Thank you. I am in the office all day, so please come any time.',
        'Still the same this morning. It is affecting my work.',
    ];

    private const INTERNAL_NOTES = [
        'Checked the logs: same fault reported by two other offices. Likely one root cause.',
        'Replacement part is not in stock. Asked procurement for a quote.',
        'Waiting for the user to be available before I go on site.',
    ];

    private const RESOLUTIONS = [
        'Replaced the faulty cable and tested. Everything works normally now.',
        'Reset the configuration and restarted the service. The user confirmed it works.',
        'Installed the missing update and restarted the computer. The problem is gone.',
        'Cleared the old data, increased the quota and checked that the user can work again.',
        'The device was replaced with a spare unit and the old one was sent for repair.',
    ];

    private ?string $passwordHash = null;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoSeeder skipped: demo data is never created in production.');

            return;
        }

        if (User::query()->where('email', $this->emailFor(self::AGENTS[0]))->exists()) {
            $this->command?->warn('DemoSeeder skipped: demo data already exists.');

            return;
        }

        fake()->seed(2026);

        DB::transaction(function (): void {
            $admin = User::query()->where('role', UserRole::Admin)->first()
                ?? throw new RuntimeException('Seed an administrator before the demo data.');
            $agents = $this->createAgents();
            $staff = $this->createStaff();
            $categories = Category::query()->where('is_active', true)->get()->keyBy('name');
            $slaHours = SlaPolicy::query()->get()->mapWithKeys(
                fn (SlaPolicy $policy): array => [$policy->priority->value => $policy->resolution_hours],
            );

            if ($categories->isEmpty() || $slaHours->count() < count(TicketPriority::cases())) {
                throw new RuntimeException('Seed categories and SLA policies before the demo data.');
            }

            $plans = $this->planTickets($slaHours->all());
            $sequence = [];

            foreach ($plans as $plan) {
                $year = $plan['created']->year;
                $sequence[$year] = ($sequence[$year] ?? 0) + 1;

                $this->createTicket(
                    $plan,
                    sprintf('HD-%d-%05d', $year, $sequence[$year]),
                    $staff->random(),
                    $agents->random(),
                    $admin,
                    $categories->random(),
                );
            }
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function createAgents(): Collection
    {
        return collect(self::AGENTS)->map(fn (string $name): User => $this->createUser($name, 'ICT', UserRole::Agent));
    }

    /**
     * @return Collection<int, User>
     */
    private function createStaff(): Collection
    {
        return collect(self::STAFF)->map(
            fn (array $person): User => $this->createUser($person[0], $person[1], UserRole::User),
        );
    }

    private function createUser(string $name, string $department, UserRole $role): User
    {
        $this->passwordHash ??= Hash::make('password');

        $user = new User(['name' => $name, 'email' => $this->emailFor($name), 'password' => $this->passwordHash, 'department' => $department]);
        $user->role = $role;
        $user->is_active = true;
        $user->save();

        return $user;
    }

    private function emailFor(string $name): string
    {
        return Str::of($name)->slug('.')->append('@helpdesk.test')->toString();
    }

    /**
     * Decide every ticket's timeline first so ticket numbers can follow creation order.
     *
     * @param  array<string, int>  $slaHours
     * @return list<array{status: TicketStatus, priority: TicketPriority, created: CarbonImmutable, due: CarbonImmutable, assigned: CarbonImmutable, started: CarbonImmutable, resolved: CarbonImmutable, closed: CarbonImmutable}>
     */
    private function planTickets(array $slaHours): array
    {
        $now = CarbonImmutable::now();
        $priorities = [
            ...array_fill(0, 3, TicketPriority::Low),
            ...array_fill(0, 5, TicketPriority::Medium),
            ...array_fill(0, 2, TicketPriority::High),
        ];
        $finishedAge = ['resolved' => [2 * 1440, 40 * 1440], 'closed' => [4 * 1440, 90 * 1440]];

        $plans = [];
        foreach (self::TICKETS_PER_STATUS as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $priority = fake()->randomElement($priorities);
                $slaMinutes = $slaHours[$priority->value] * 60;
                $finished = isset($finishedAge[$status]);

                // About a fifth of unresolved tickets are past their due time.
                $age = match (true) {
                    $finished => fake()->numberBetween(...$finishedAge[$status]),
                    fake()->boolean(22) => fake()->numberBetween($slaMinutes + 20, $slaMinutes + 4 * 1440),
                    default => fake()->numberBetween(30, $slaMinutes - 30),
                };
                $created = $now->subMinutes($age);
                $assigned = min($now->subMinutes(5), $created->addMinutes(fake()->numberBetween(5, max(6, intdiv($slaMinutes * 15, 100)))));
                $started = max($assigned, min($now->subMinute(), $assigned->addMinutes(fake()->numberBetween(5, max(6, intdiv($slaMinutes * 20, 100))))));

                // About one finished ticket in seven is resolved after its due time.
                $late = fake()->boolean(15);
                $workMinutes = (int) round($slaMinutes * ($late ? fake()->randomFloat(2, 1.1, 2.5) : fake()->randomFloat(2, 0.2, 0.95)));
                $resolved = min($now->subHour(), max($started->addMinutes(5), $created->addMinutes($workMinutes)));
                $closed = min($now->subMinutes(10), $resolved->addHours(fake()->numberBetween(1, 72)));

                $plans[] = [
                    'status' => TicketStatus::from($status),
                    'priority' => $priority,
                    'created' => $created,
                    'due' => $created->addMinutes($slaMinutes),
                    'assigned' => $assigned,
                    'started' => $started,
                    'resolved' => $resolved,
                    'closed' => $closed,
                ];
            }
        }

        usort($plans, fn (array $a, array $b): int => $a['created'] <=> $b['created']);

        return $plans;
    }

    /**
     * @param  array{status: TicketStatus, priority: TicketPriority, created: CarbonImmutable, due: CarbonImmutable, assigned: CarbonImmutable, started: CarbonImmutable, resolved: CarbonImmutable, closed: CarbonImmutable}  $plan
     */
    private function createTicket(array $plan, string $number, User $requester, User $agent, User $admin, Category $category): void
    {
        $now = CarbonImmutable::now();
        $status = $plan['status'];
        $assignedOnce = $status !== TicketStatus::Open;
        $text = fake()->randomElement(self::TICKET_TEXT[$category->name] ?? self::TICKET_TEXT['Other']);

        $finishedAt = match ($status) {
            TicketStatus::Resolved => $plan['resolved'],
            TicketStatus::Closed => $plan['closed'],
            default => null,
        };

        $escalationLevel = 0;
        $escalatedAt = null;
        $escalatedWhen = [];

        $overdueSince = $plan['due'];
        $endOfWork = in_array($status, TicketStatus::finished(), true) ? $plan['resolved'] : $now;
        if ($overdueSince->addMinutes(15) < $endOfWork) {
            $escalationLevel = fake()->numberBetween(1, 3);
            for ($level = 1; $level <= $escalationLevel; $level++) {
                $escalatedWhen[$level] = min($endOfWork->subMinute(), $overdueSince->addMinutes(15 * $level));
            }
            $escalatedAt = end($escalatedWhen);
        }

        $ticket = Ticket::factory()->create([
            'ticket_no' => $number,
            'requester_id' => $requester->id,
            'agent_id' => $assignedOnce ? $agent->id : null,
            'category_id' => $category->id,
            'title' => $text[0],
            'description' => $text[1],
            'priority' => $plan['priority'],
            'status' => $status,
            'due_at' => $plan['due'],
            'escalation_level' => $escalationLevel,
            'escalated_at' => $escalatedAt,
            'resolution' => in_array($status, TicketStatus::finished(), true) ? fake()->randomElement(self::RESOLUTIONS) : null,
            'resolved_at' => in_array($status, TicketStatus::finished(), true) ? $plan['resolved'] : null,
            'closed_at' => $status === TicketStatus::Closed ? $plan['closed'] : null,
            'created_at' => $plan['created'],
            'updated_at' => $finishedAt ?? $escalatedAt ?? ($assignedOnce ? $plan['started'] : $plan['created']),
        ]);

        $this->history($ticket, $requester->id, 'created', null, TicketStatus::Open, 'Ticket submitted', $plan['created']);

        if ($assignedOnce) {
            $this->history($ticket, $admin->id, 'assigned', TicketStatus::Open, TicketStatus::Assigned, 'Assigned to '.$agent->name, $plan['assigned']);
        }

        if (in_array($status, [TicketStatus::InProgress, TicketStatus::Resolved, TicketStatus::Closed], true)) {
            $this->history($ticket, $agent->id, 'status_changed', TicketStatus::Assigned, TicketStatus::InProgress, null, $plan['started']);
            $this->responses($ticket, $requester, $agent, $plan['started']);
        }

        foreach ($escalatedWhen as $level => $when) {
            $this->history($ticket, null, 'escalated', null, null, "Escalated to level {$level}: past due time", $when);
        }

        if (in_array($status, TicketStatus::finished(), true)) {
            $this->history($ticket, $agent->id, 'resolved', TicketStatus::InProgress, TicketStatus::Resolved, 'Resolution recorded', $plan['resolved']);
        }

        if ($status === TicketStatus::Closed) {
            $this->history($ticket, $requester->id, 'closed', TicketStatus::Resolved, TicketStatus::Closed, 'Confirmed by requester', $plan['closed']);
        }

        Notification::factory()->create([
            'user_id' => $requester->id,
            'ticket_id' => $ticket->id,
            'message' => sprintf('Ticket %s is now %s.', $number, mb_strtolower($status->label())),
            'channel' => NotificationChannel::InApp,
            'read_at' => $status === TicketStatus::Closed || fake()->boolean(40) ? $finishedAt ?? $now : null,
            'created_at' => $finishedAt ?? ($assignedOnce ? $plan['started'] : $plan['created']),
        ]);
    }

    private function history(Ticket $ticket, ?int $changedBy, string $action, ?TicketStatus $old, ?TicketStatus $new, ?string $details, CarbonImmutable $at): void
    {
        TicketHistory::factory()->create([
            'ticket_id' => $ticket->id,
            'changed_by' => $changedBy,
            'action' => $action,
            'old_status' => $old,
            'new_status' => $new,
            'details' => $details,
            'created_at' => $at,
        ]);
    }

    private function responses(Ticket $ticket, User $requester, User $agent, CarbonImmutable $from): void
    {
        $at = $from->addMinutes(fake()->numberBetween(5, 60));
        $this->response($ticket, $agent, fake()->randomElement(self::FIRST_REPLIES), false, $at);

        if (fake()->boolean(60)) {
            $at = $at->addMinutes(fake()->numberBetween(10, 180));
            $this->response($ticket, $requester, fake()->randomElement(self::REQUESTER_REPLIES), false, $at);
        }

        if (fake()->boolean(40)) {
            $at = $at->addMinutes(fake()->numberBetween(10, 120));
            $this->response($ticket, $agent, fake()->randomElement(self::INTERNAL_NOTES), true, $at);
        }
    }

    private function response(Ticket $ticket, User $author, string $message, bool $internal, CarbonImmutable $at): void
    {
        Response::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'message' => $message,
            'is_internal' => $internal,
            'created_at' => min($at, CarbonImmutable::now()),
            'updated_at' => min($at, CarbonImmutable::now()),
        ]);
    }
}
