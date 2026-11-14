<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        // Only active accounts get a link. The answer is the same whether or not one was sent, so this
        // form cannot be used to find out which email addresses have an account.
        Password::broker(config('fortify.passwords'))->sendResetLink([
            'email' => $request->validated('email'),
            'is_active' => true,
        ]);

        $minutes = (int) config('auth.passwords.'.config('fortify.passwords').'.expire');

        return back()->with('status', "If an account exists for that email address, we have sent a link to reset the password. It works for {$minutes} minutes.");
    }
}
