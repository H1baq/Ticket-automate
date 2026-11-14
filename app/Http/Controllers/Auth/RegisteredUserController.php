<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', ['domains' => config('hdtms.auth.allowed_email_domains')]);
    }

    public function store(RegisterRequest $request, UserService $users): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string, department?: string|null} $data */
        $data = $request->safe()->only(['name', 'email', 'password', 'department']);

        Auth::login($users->register($data));
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('toast', ['type' => 'success', 'message' => 'Your account is ready.']);
    }
}
