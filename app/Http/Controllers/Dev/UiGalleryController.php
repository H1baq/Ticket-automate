<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\TicketHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

/**
 * Local-only gallery of every interface component, filled with sample data
 * that is never stored. It exists so the design can be reviewed before features use it.
 */
class UiGalleryController extends Controller
{
    private const MAX_DELAY_SECONDS = 2;

    public function show(Request $request): View
    {
        $this->ensureLocal();

        // Shared like the framework does, so nested components see the sample error too.
        ViewFacade::share('errors', (new ViewErrorBag())->put('default', new MessageBag([
            'email' => ['Enter an email address like name@example.com.'],
        ])));

        $role = UserRole::tryFrom((string) $request->query('role')) ?? UserRole::Admin;
        $rows = $this->sampleTickets($request->query('sort'), $request->query('direction') === 'desc');

        return view('dev.ui', [
            'role' => $role,
            'rows' => $rows,
            'paginator' => new LengthAwarePaginator($rows, 142, 20, 2, ['path' => $request->url()]),
            'history' => $this->sampleHistory(),
            'categories' => ['hardware' => 'Hardware', 'software' => 'Software', 'network' => 'Network'],
            'statuses' => TicketStatus::cases(),
            'priorities' => TicketPriority::cases(),
            'filters' => $request->only(['search', 'status', 'priority']),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $this->ensureLocal();

        sleep(min(self::MAX_DELAY_SECONDS, max(0, $request->integer('delay'))));

        return redirect()
            ->route('dev.ui')
            ->with('toast', ['type' => 'success', 'message' => 'Ticket HD-2026-00142 created. We will email you when an agent picks it up.']);
    }

    private function ensureLocal(): void
    {
        abort_unless(app()->environment('local'), 404);
    }

    /**
     * @return list<array{number: string, title: string, requester: string, status: TicketStatus, priority: TicketPriority, due: CarbonImmutable, overdue: bool}>
     */
    private function sampleTickets(mixed $sort, bool $descending): array
    {
        $now = CarbonImmutable::now();
        $rows = [
            ['HD-2026-00142', 'Printer by the registry counter shows paper jam', 'Mercy Njeri', TicketStatus::InProgress, TicketPriority::Medium, $now->addHours(3)],
            ['HD-2026-00141', 'Cannot open the finance system after the update', 'Kevin Mwangi', TicketStatus::Open, TicketPriority::High, $now->subHours(2)],
            ['HD-2026-00140', 'Request for a second monitor', 'Lucy Akinyi', TicketStatus::Assigned, TicketPriority::Low, $now->addHours(20)],
            ['HD-2026-00139', 'Wi-Fi keeps disconnecting in the training room', 'Naomi Chepkoech', TicketStatus::InProgress, TicketPriority::High, $now->subMinutes(35)],
            ['HD-2026-00138', 'New staff member needs an email account', 'Esther Wambui', TicketStatus::Resolved, TicketPriority::Medium, $now->subHours(5)],
            ['HD-2026-00137', 'Forgot my password and locked out', 'Abdi Warsame', TicketStatus::Closed, TicketPriority::High, $now->subDay()],
        ];

        $tickets = array_map(fn (array $row): array => [
            'number' => $row[0],
            'title' => $row[1],
            'requester' => $row[2],
            'status' => $row[3],
            'priority' => $row[4],
            'due' => $row[5],
            'overdue' => in_array($row[3], TicketStatus::unresolved(), true) && $row[5]->isPast(),
        ], $rows);

        $sortKey = match ($sort) {
            'number' => 'number',
            'due' => 'due',
            default => null,
        };

        if ($sortKey !== null) {
            usort($tickets, fn (array $a, array $b): int => $descending ? $b[$sortKey] <=> $a[$sortKey] : $a[$sortKey] <=> $b[$sortKey]);
        }

        return $tickets;
    }

    private function sampleUser(int $id, string $name): User
    {
        $user = new User(['name' => $name]);
        $user->id = $id;

        return $user;
    }

    /**
     * @return Collection<int, TicketHistory>
     */
    private function sampleHistory(): Collection
    {
        $requester = $this->sampleUser(11, 'Wanjiku Kamau');
        $admin = $this->sampleUser(1, 'System Administrator');
        $agent = $this->sampleUser(12, 'Brian Otieno');
        $start = CarbonImmutable::now()->subHours(7);

        $entry = function (string $action, ?User $by, ?TicketStatus $old, ?TicketStatus $new, ?string $details, int $minutes) use ($start): TicketHistory {
            $history = new TicketHistory(['action' => $action, 'old_status' => $old, 'new_status' => $new, 'details' => $details]);
            $history->created_at = $start->addMinutes($minutes);
            $history->changed_by = $by?->id;
            $history->setRelation('changedBy', $by);

            return $history;
        };

        return collect([
            $entry('created', $requester, null, TicketStatus::Open, 'Ticket submitted with high priority.', 0),
            $entry('assigned', $admin, TicketStatus::Open, TicketStatus::Assigned, 'Assigned to Brian Otieno.', 25),
            $entry('status_changed', $agent, TicketStatus::Assigned, TicketStatus::InProgress, null, 70),
            $entry('escalated', null, null, null, 'Escalated to level 1: past its due time.', 250),
        ]);
    }
}
