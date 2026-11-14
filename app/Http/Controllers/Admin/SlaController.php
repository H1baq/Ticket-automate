<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TicketPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSlaPoliciesRequest;
use App\Models\SlaPolicy;
use App\Models\User;
use App\Services\SlaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SlaController extends Controller
{
    public function __construct(private readonly SlaService $sla)
    {
    }

    public function edit(): View
    {
        $this->authorize('viewAny', SlaPolicy::class);

        return view('admin.sla.edit', [
            'hours' => $this->sla->hoursByPriority(),
            'priorities' => array_reverse(TicketPriority::cases()),
        ]);
    }

    public function update(UpdateSlaPoliciesRequest $request): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $changes = $this->sla->update($request->hours(), $admin);

        return redirect()->route('admin.sla.edit')->with('toast', [
            'type' => 'success',
            'message' => $changes === []
                ? 'Nothing changed. These are already the resolution times.'
                : 'Resolution times saved. They apply to tickets reported from now on.',
        ]);
    }
}
