<header class="topbar">
    <button type="button" class="icon-button d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open menu">
        <x-icon name="menu" />
    </button>

    @if ($searchUrl)
        <form class="topbar-search" role="search" method="GET" action="{{ $searchUrl }}">
            <label class="visually-hidden" for="topbar-search">Search tickets</label>
            <x-icon name="search" />
            <input id="topbar-search" class="form-control form-control-sm" type="search" name="search" value="{{ request('search') }}" placeholder="Search tickets" autocomplete="off" data-shortcut-search>
            <kbd aria-hidden="true">/</kbd>
        </form>
    @endif

    <div class="topbar-actions">
        @if ($notificationsUrl)
            <a class="icon-button" href="{{ $notificationsUrl }}" aria-label="{{ $notificationsLabel() }}">
                <x-icon name="bell" />
                @if ($unread > 0)
                    <span class="count-badge" aria-hidden="true">{{ $unread > 99 ? '99+' : $unread }}</span>
                @endif
            </a>
        @endif

        <div class="dropdown">
            <button type="button" class="user-menu-button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu for {{ $name }}">
                <span class="avatar" aria-hidden="true">{{ $initials() }}</span>
                <span class="d-none d-md-inline">{{ $name }}</span>
                <x-icon name="chevron-down" class="d-none d-md-inline" />
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="px-3 py-1">
                    <div class="fw-semibold">{{ $name }}</div>
                    <div class="text-muted small">{{ $email }}</div>
                    <div class="text-muted small">{{ $roleLabel }}</div>
                </li>
                @if ($logoutUrl)
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ $logoutUrl }}">
                            @csrf
                            <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                                <x-icon name="log-out" /> Sign out
                            </button>
                        </form>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</header>
