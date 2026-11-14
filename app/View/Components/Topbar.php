<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\Component;

class Topbar extends Component
{
    public string $name;

    public string $email;

    public string $roleLabel;

    public int $unread;

    public ?string $notificationsUrl;

    public ?string $searchUrl;

    public ?string $logoutUrl;

    public function __construct(public bool $preview = false, ?UserRole $role = null)
    {
        $user = auth()->user();

        if ($user instanceof User) {
            $this->name = $user->name;
            $this->email = $user->email;
            $this->roleLabel = $user->role->label();
            $this->unread = $user->notifications()->unread()->count();
        } else {
            $this->name = 'Preview user';
            $this->email = 'preview@example.test';
            $this->roleLabel = ($role ?? UserRole::User)->label();
            $this->unread = $preview ? 3 : 0;
        }

        $this->notificationsUrl = $this->urlFor('notifications.index');
        $this->searchUrl = $this->urlFor('tickets.index');
        $this->logoutUrl = $this->urlFor('logout');
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    public function notificationsLabel(): string
    {
        return $this->unread > 0 ? "Notifications, {$this->unread} unread" : 'Notifications';
    }

    public function render(): View
    {
        return view('components.topbar');
    }

    private function urlFor(string $routeName): ?string
    {
        if (Route::has($routeName)) {
            return route($routeName);
        }

        return $this->preview ? '#' : null;
    }
}
