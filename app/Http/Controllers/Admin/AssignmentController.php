<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TicketPriority;
use App\Exceptions\AssignmentNotAllowed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignmentQueueRequest;
use App\Http\Requests\Admin\AssignTicketRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function __construct(private readonly AssignmentService $assignments)
    {
    }

    public function index(AssignmentQueueRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.assignments.index', [
            'tickets' => $this->assignments->paginateQueue($filters, (int) config('hdtms.per_page')),
            'counts' => $this->assignments->queueCounts(),
            'agents' => $this->assignments->agentsWithOpenTickets(),
            'filters' => $filters,
            'priorities' => TicketPriority::cases(),
        ]);
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $agent = User::query()->findOrFail($request->agentId());
        $wasAssigned = $ticket->agent_id !== null;

        try {
            $this->assignments->assign($ticket, $agent, $admin);
        } catch (AssignmentNotAllowed $refusal) {
            return back()->with('toast', ['type' => 'error', 'message' => $refusal->getMessage()]);
        }

        $verb = $wasAssigned ? 'reassigned' : 'assigned';

        return back()->with('toast', ['type' => 'success', 'message' => "Ticket {$ticket->ticket_no} {$verb} to {$agent->name}."]);
    }
}
