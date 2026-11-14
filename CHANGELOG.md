# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Laravel 12 application skeleton configured for HDTMS: app name, Africa/Nairobi
  timezone and a 30-minute session lifetime.
- Quality tooling: Laravel Pint, Larastan (level 6), PHPUnit on MySQL with an
  in-memory SQLite option for unit tests, Laravel Dusk, ESLint, Prettier and
  Stylelint.
- Composer scripts `lint`, `format`, `analyse`, `test`, `test:fast` and `quality`,
  and npm scripts `lint`, `format`, `build` and `dev`.
- Git hooks enforcing Conventional Commits, blocking `.env*` files and files over
  2 MB, and linting staged files.
- GitHub Actions pipeline with lint, analyse, test, build, e2e and security jobs,
  an 80% coverage gate for `app/Services`, Dependabot and a pull request template.
- README, security policy and this changelog.
- Database schema for the seven application tables, matching the SDS data
  dictionary, plus the framework tables for sessions, cache and queues.
- Enums `UserRole`, `TicketPriority`, `TicketStatus` and `NotificationChannel`.
  `TicketStatus::canTransitionTo()` is the single definition of allowed status
  changes.
- Eloquent models with relations and the `open`, `overdue`, `forAgent` and
  `forRequester` ticket scopes. Ticket history is append-only. Lazy loading is
  prevented outside production.
- Factories for every model.
- Seeders for categories, SLA policies and the first administrator
  (`SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD`), and a demo seeder with 150
  tickets that never runs in production.

- Design system: Sass tokens and a Bootstrap 5.3 theme (only the modules in use),
  self-hosted Source Sans 3 and JetBrains Mono, and Blade components for layouts,
  role-aware navigation, badges, tables, forms, modals, toasts, pagination and the
  ticket timeline.
- JavaScript behaviours as separate modules: toasts, confirm modal, keyboard
  shortcuts (`n`, `/`, `g` then `d`), disable-on-submit, copy ticket number and
  character counter. No inline scripts.
- Friendly 403, 404, 419, 429 and 500 pages.
- Local-only component gallery at `/dev/ui`.
- Branding settings `ORGANIZATION_NAME` and `ORGANIZATION_LOGO`.

- Authentication with Laravel Fortify and our own views: sign-in with a 5-failure,
  15-minute lockout, rate limits on sign-in, registration and password reset, 30-minute
  idle sessions, password reset links that last 60 minutes, and optional
  self-registration limited to allowed email domains.
- `role`, `active` and `registration` middleware, and `UserPolicy`.
- Administrator user management: search, filters, sorting, create, edit, deactivate and
  reactivate, with a queued email that lets a new user choose a password.
- A minimal dashboard as the landing page for every role.

- Ticket submission: a form with a category guide and priorities explained in plain words with the
  time we aim for, one private attachment (JPG, PNG or PDF, 5 MB), numbers `HD-YYYY-NNNNN` issued
  one at a time under a lock, and a due time from the SLA policy.
- "My tickets" list with counts, filters kept in the address, sortable columns and 20 a page, and a
  ticket page with description, conversation, history and an authorized attachment download.
- `TicketPolicy`: the requester, the assigned agent and administrators may view a ticket.
- `composer serve`, which raises PHP's 2 MB upload limit for local development.

- Agents and administrators can report problems of their own, with the same "My tickets" list and
  "New ticket" form as staff. Whoever reported a ticket never sees its internal notes, whatever their role.

- Ticket assignment: an assignment queue (unassigned, assigned and overdue tickets, most urgent first)
  with each active agent's open-ticket count in the picker, assignment and reassignment at any status
  before closed, history rows naming both agents, and a `TicketAssigned` event.
- SLA settings page: resolution time per priority as a whole number of hours from 1 to 720, applied to
  tickets reported afterwards only.
- Escalation: `tickets:escalate-overdue`, scheduled every 15 minutes without overlap, raises the level of
  unresolved tickets past their due time, records it in the history and dispatches `TicketEscalated`.
- Overdue counts on every dashboard, with the overdue count in red.

### Future work

- Raising a ticket on behalf of another user. Every ticket is currently reported by the person who is
  signed in, and the form cannot set a different requester.

### Fixed

- Visually hidden labels inside wide tables no longer make a page scroll sideways on a phone.

### Changed

- Priority is no longer preselected on the new ticket form; the person must choose it.
- Dependencies are resolved for PHP 8.2, so installs work on PHP 8.2 and 8.3 (Symfony 7.4, not 8).
- Dates are immutable application-wide.
- The private disk is no longer served by address.
- The home address now leads to the dashboard, or to sign-in for visitors.
- The sidebar shortcut legend lists only shortcuts that work for the signed-in user.

- The pre-commit hook now says clearly when PHP cannot be found.

### Removed

- Tailwind CSS, axios and Laravel Sail from the default Laravel skeleton.
