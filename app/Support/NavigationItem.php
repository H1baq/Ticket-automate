<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One entry of the sidebar. $activeOn lists the route-name patterns that mark it as the current page.
 */
final readonly class NavigationItem
{
    /**
     * @param  list<string>  $activeOn
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $icon,
        public array $activeOn,
        public ?string $shortcut = null,
        public string $group = 'Work',
    ) {
    }
}
