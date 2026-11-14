<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Requests\Tickets\IndexTicketsRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $tickets)
    {
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
            'responses' => fn ($responses) => $responses->visibleTo($viewer)->oldest('created_at')->oldest('id')->with('user'),
        ]);

        return view('tickets.show', ['ticket' => $ticket, 'viewer' => $viewer]);
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
