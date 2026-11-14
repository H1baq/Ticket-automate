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

### Changed

- The pre-commit hook now says clearly when PHP cannot be found.

### Removed

- Tailwind CSS, axios and Laravel Sail from the default Laravel skeleton.
