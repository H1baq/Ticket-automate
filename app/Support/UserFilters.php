<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserRole;

/**
 * What the administrator asked for on the users list: a search, filters and a sort order.
 */
final readonly class UserFilters
{
    public const SORTABLE = ['name', 'email', 'role', 'department', 'created_at'];

    public function __construct(
        public ?string $search = null,
        public ?UserRole $role = null,
        public ?bool $active = null,
        public string $sort = 'name',
        public string $direction = 'asc',
    ) {
    }
}
