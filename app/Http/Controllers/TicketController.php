<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Requests\Tickets\IndexTicketsRequest;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $tickets, private readonly AssignmentService $assignments)
    {
    }

    public function create(): View
    {
        $this->authorize('create', Ticket::class);

        return view('tickets.create', [
            'categories' => $this->tickets->activeCategories(),
            'priorities' => TicketPriority::cases(),
            'hours' => $this->tickets->resolutionHoursByPriority(),
        ]);
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ticket = $this->tickets->create($user, $request->ticketData(), $request->file('attachment'));

        return redirect()->route('tickets.show', $ticket)->with('toast', [
            'type' => 'success',
            'message' => "Ticket {$ticket->ticket_no} created. We'll email you when an agent picks it up.",
        ]);
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        /** @var User $viewer */
        $viewer = $request->user();

        $ticket->load([
            'requester',
            'agent',
            'category',
            'history' => fn ($history) => $history->oldest('created_at')->oldest('id')->with('changedBy'),
            'responses' => fn ($responses) => $responses->visibleTo($viewer, $ticket->requester_id)->oldest('created_at')->oldest('id')->with('user'),
        ]);

        return view('tickets.show', [
            'ticket' => $ticket,
            'viewer' => $viewer,
            'agents' => $viewer->can('assign', $ticket) ? $this->assignments->agentsWithOpenTickets() : null,
        ]);
    }

    public function index(IndexTicketsRequest $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->filters();

        return view('tickets.index', [
            'tickets' => $this->tickets->paginateForRequester($user, $filters, (int) config('hdtms.per_page')),
            'summary' => $this->tickets->summaryFor($user),
            'filters' => $filters,
            'statuses' => TicketStatus::cases(),
            'priorities' => TicketPriority::cases(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
