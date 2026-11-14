<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly TicketService $tickets, private readonly AssignmentService $assignments)
    {
    }

    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('dashboard', [
            'user' => $user,
            'reported' => $this->tickets->summaryFor($user),
            'assigned' => $user->role === UserRole::Agent ? $this->tickets->assignedSummaryFor($user) : null,
            'queue' => $user->role === UserRole::Admin ? $this->assignments->queueCounts() : null,
        ]);
    }
}
