<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Navigation;
use App\Support\NavigationItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class Sidebar extends Component
{
    public ?UserRole $role;

    /**
     * @param  bool  $preview  Show every item of the role even when its page does not exist yet (the /dev/ui gallery).
     * @param  string|null  $current  Label of the item to mark as current in preview mode.
     */
    public function __construct(
        ?UserRole $role = null,
        public bool $preview = false,
        public ?string $current = null,
    ) {
        $user = auth()->user();
        $this->role = $role ?? ($user instanceof User ? $user->role : null);
    }

    /**
     * Items grouped by their heading, limited to pages that exist.
     *
     * @return array<string, list<array{label: string, icon: string, href: string, active: bool, shortcut: string|null}>>
     */
    public function groups(): array
    {
        if (! $this->role instanceof UserRole) {
            return [];
        }

        $groups = [];
        foreach (Navigation::for($this->role) as $item) {
            if (! $this->preview && ! Route::has($item->route)) {
                continue;
            }

            $groups[$item->group][] = [
                'label' => $item->label,
                'icon' => $item->icon,
                'href' => $this->preview ? '#' : route($item->route),
                'active' => $this->isActive($item),
                'shortcut' => $item->shortcut,
            ];
        }

        return $groups;
    }

    public function organization(): string
    {
        return (string) config('hdtms.organization.name');
    }

    public function logo(): ?string
    {
        $logo = config('hdtms.organization.logo');

        return is_string($logo) && $logo !== '' ? asset($logo) : null;
    }

    public function render(): View
    {
        return view('components.sidebar');
    }

    private function isActive(NavigationItem $item): bool
    {
        if ($this->preview) {
            return $item->label === $this->current;
        }

        return request()->routeIs(...$item->activeOn);
    }
}
