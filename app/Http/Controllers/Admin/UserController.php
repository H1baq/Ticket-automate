<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexUsersRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users)
    {
    }

    public function index(IndexUsersRequest $request): View
    {
        return view('admin.users.index', [
            'users' => $this->users->paginate($request->filters(), (int) config('hdtms.per_page')),
            'filters' => $request->filters(),
            'roles' => UserRole::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', ['roles' => UserRole::cases()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->accountData());

        return redirect()->route('admin.users.index')->with('toast', [
            'type' => 'success',
            'message' => "User {$user->name} created. We emailed {$user->email} a link to set a password.",
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [
            'account' => $user,
            'roles' => UserRole::cases(),
            'canChangeRole' => request()->user()?->can('changeRole', $user) ?? false,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, $request->accountData());

        return redirect()->route('admin.users.index')->with('toast', [
            'type' => 'success',
            'message' => "Changes to {$user->name} saved.",
        ]);
    }

    public function deactivate(User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        $this->users->deactivate($user);

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => "{$user->name} is deactivated and signed out. Their tickets stay in the system.",
        ]);
    }

    public function reactivate(User $user): RedirectResponse
    {
        $this->authorize('reactivate', $user);

        $this->users->reactivate($user);

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => "{$user->name} can sign in again.",
        ]);
    }
}
