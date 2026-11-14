# HDTMS — Help Desk Ticketing and Management System

A web-based IT help desk. Staff report IT problems as tickets, support agents
resolve them, and administrators assign tickets, manage the system and produce
reports. Built with Laravel 12 as a KCA University final-year project, and
structured so it can be rebranded and offered to other organizations.

## What it does

1. **Ticket submission** — capture, categorize and prioritize requests. Each
   ticket gets a unique number (`HD-YYYY-NNNNN`) and a due time from the SLA
   policy.
2. **Ticket assignment** — an administrator routes each ticket to an agent.
   Tickets unresolved past their priority's resolution hours escalate
   automatically.
3. **Ticket tracking** — every status change and resolution is kept in the
   history, and the requester is notified of each update in the app and by
   email.
4. **Reporting** — ticket volumes, resolution times and agent workload,
   exportable to PDF and CSV.

Accounts have one of three roles: user, agent or admin.

## Requirements

- PHP 8.2 or newer (8.3 or 8.4 recommended) with Composer
- MySQL 8.0 (MariaDB also works)
- Node.js 22 or newer with npm
- Git

## Local setup on macOS

These steps use [php.new](https://php.new) for PHP and Composer, and
[DBngin](https://dbngin.com) for MySQL. No Homebrew is needed.

1. **Install PHP and Composer.** Run the one-line installer from php.new, then
   open a new terminal and check `php -v` and `composer --version`.
2. **Install DBngin**, create a MySQL 8 service on `127.0.0.1:3306` and start
   it. The defaults are user `root` with an empty password, which is what
   `.env.example` expects.
3. **Clone the project and install dependencies.**

    ```bash
    git clone https://github.com/H1baq/Ticket-automate.git
    cd Ticket-automate
    composer install
    npm install
    ```

    `composer install` also points Git at the hooks in `.githooks/` (see
    [Git hooks](#git-hooks)).

4. **Configure the environment.**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

    Every setting in `.env.example` is documented. Change the database values
    only if your MySQL differs from the DBngin defaults.

5. **Create the databases.** The macOS setup has no `mysql` client, so create
   both databases through PHP:

    ```bash
    php -r '$pdo = new PDO("mysql:host=127.0.0.1;port=3306", "root", ""); foreach (["hdtms", "hdtms_test"] as $name) { $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); }'
    ```

    `hdtms` is the application database and `hdtms_test` is used by the test
    suite.

6. **Create the tables and seed the data.** Set `SEED_ADMIN_EMAIL` and
   `SEED_ADMIN_PASSWORD` in `.env` first (use at least 8 characters), then run:

    ```bash
    php artisan migrate --seed
    ```

    This creates the six default categories, the SLA times (high 4 hours,
    medium 8 hours, low 24 hours) and your administrator account. Outside
    production it also creates demo staff, agents and about 150 tickets. Every
    demo account (for example `brian.otieno@helpdesk.test`) has the password
    `password`. To start again with fresh demo data, run
    `php artisan migrate:fresh --seed`.

7. **Build the assets and start the app.**

    ```bash
    npm run build
    composer serve
    ```

    Open <http://localhost:8000>. For frontend work, run `npm run dev` in a
    second terminal instead of `npm run build`.

    Use `composer serve` rather than `php artisan serve`. It starts PHP's built-in server with
    the upload limit raised from PHP's default of 2 MB, so ticket attachments of up to 5 MB
    work. If you start the server another way, set `upload_max_filesize = 6M` and
    `post_max_size = 8M` in your `php.ini`.

## Commands

| Command                             | What it does                                           |
| ----------------------------------- | ------------------------------------------------------ |
| `composer quality`                  | Pint check, Larastan (level 6) and the full test suite |
| `composer lint` / `composer format` | Check or fix PHP style with Pint                       |
| `composer analyse`                  | Static analysis with Larastan                          |
| `composer test`                     | Full PHPUnit suite on MySQL (`hdtms_test`)             |
| `composer test:fast`                | Unit suite on in-memory SQLite                         |
| `npm run lint`                      | ESLint, Stylelint and Prettier check                   |
| `npm run format`                    | Rewrite JS and SCSS with Prettier                      |
| `npm run build` / `npm run dev`     | Production build / Vite dev server                     |
| `composer serve`                    | Start the app on port 8000 with the upload limits set  |

### Browser tests

Dusk drives a real Chrome against a running server. Start the app, then run
the tests in a second terminal:

```bash
composer serve
php artisan dusk
```

If Chrome updates and the driver no longer matches, run
`php artisan dusk:chrome-driver --detect`.

### Code coverage

The static PHP installed by php.new has no coverage driver (Xdebug or PCOV),
so `composer test` runs without coverage on a Mac. Coverage is measured in
CI, which fails the build when line coverage of `app/Services` falls below
80%.

## Accounts and signing in

Open <http://localhost:8000> and sign in with the administrator you seeded
(`SEED_ADMIN_EMAIL`). The demo data also has staff and agent accounts, for example
`wanjiku.kamau@helpdesk.test` (staff) and `brian.otieno@helpdesk.test` (agent), all with
the password `password`.

- **Roles:** staff user, support agent and administrator. Each sees only its own menu.
- **Lockout:** five wrong passwords in a row lock an account for 15 minutes. Every
  failure shows the same message, "Invalid email or password.", so the form never
  reveals which emails have accounts.
- **Sessions:** a session ends after 30 idle minutes. There is no "remember me".
- **Password reset:** "Forgot your password?" sends a link that works for 60 minutes and
  once. With `MAIL_MAILER=log` the email is written to `storage/logs/laravel.log`.
- **Self-registration:** off by default. To allow it, set `SELF_REGISTRATION_ENABLED=true`
  in `.env`, and optionally `ALLOWED_EMAIL_DOMAINS=example.ac.ke` so only work addresses
  can register. New accounts are always staff users.
- **Administrators** manage accounts under **Users**: search, filter, create, edit role
  and department, deactivate and reactivate. Accounts are never deleted. When you create
  a user, an email with a link to choose a password is queued, so run
  `php artisan queue:work` or no email is sent.

## Tickets

Anyone with an account reports a problem under **My tickets** (or presses `n`). Each person sees only the tickets they reported there. Each ticket gets a number like
`HD-2026-00142` and a due time from the SLA hours of its priority (high 4, medium 8, low 24).
A ticket may have one attachment: a JPG, PNG or PDF of up to 5 MB. Attachments are stored
privately and can only be downloaded by the person who reported the ticket, the agent it is
assigned to, and administrators. Only the numbers matter in the address: `/tickets/HD-2026-00142`.

## Assignment, SLA and escalation

Administrators work from the sidebar:

- **Assignment queue** lists unassigned tickets, the most urgent (earliest due time) first, with
  each active agent's open-ticket count beside the picker. The **Assigned** tab lets you move a
  ticket to another agent at any status before closed, and **Overdue** lists every unresolved
  ticket past its due time. The first assignment moves a ticket from open to assigned.
- **SLA settings** sets the resolution time of each priority, a whole number of hours from 1 to 720. A new time applies only to tickets reported after you save; tickets already reported keep
  the due time they were given.
- **Escalation:** every 15 minutes a ticket that is unresolved past its due time goes up one
  level, and the history records it as made by the system. Administrators are always the ones to
  be told, and the assigned agent too from level 2. A ticket keeps going up one level every
  15 minutes until it is resolved, up to level 255. Overdue tickets carry a red "Overdue" label
  on every list and dashboard.

A ticket cannot be assigned to the person who reported it, to a deactivated agent, or once it is
closed.

## Design system

The interface is built from Blade components in `resources/views/components` and
Sass in `resources/scss` (`_tokens.scss` holds every colour, size and font). To
see every component with sample data, open <http://localhost:8000/dev/ui> while
`APP_ENV=local`. Add `?role=user`, `?role=agent` or `?role=admin` to preview each
sidebar. The page returns 404 in every other environment.

Keyboard shortcuts: `/` focuses search, `n` opens a new ticket, and `g` then `d`
goes to the dashboard. They are ignored while you type in a field.

To rebrand the product, set `ORGANIZATION_NAME` and `ORGANIZATION_LOGO` in `.env`.

## Scheduler and queue

Notifications and emails are queued, and escalation (and later auto-close) run on the
scheduler. Both need to be running for the full workflow. Without the scheduler no ticket
is ever escalated:

```bash
php artisan queue:work               # processes queued notifications and emails
php artisan schedule:work            # runs scheduled tasks every minute (local use)
php artisan tickets:escalate-overdue # escalate overdue tickets now, by hand
```

`tickets:escalate-overdue` is scheduled every 15 minutes and never overlaps with itself, so
running it by hand while the scheduler is on is safe: a ticket is never escalated twice within
15 minutes.

In production, use Supervisor for `queue:work` and a cron entry
`* * * * * php /path/to/artisan schedule:run` for the scheduler. The
deployment guide will cover both.

Locally, emails are written to `storage/logs/laravel.log` (`MAIL_MAILER=log`).
To read them in a browser, run [Mailpit](https://mailpit.axllent.org) and set
`MAIL_MAILER=smtp` with port `1025`.

## Git hooks

`composer install` runs `git config core.hooksPath .githooks`. To enable them
by hand:

```bash
git config core.hooksPath .githooks
```

- **pre-commit** — refuses `.env*` files (except `.env.example`) and files
  over 2 MB, runs Pint on staged PHP files, and runs ESLint, Stylelint and
  Prettier on staged JS and SCSS.
- **commit-msg** — enforces the commit format below and rejects
  `Co-Authored-By:` lines and AI attribution text.

## Branches and commits

- `main` is always releasable, and `develop` is the integration branch.
  Milestones are tagged on `main` (`v0.1.0` to `v1.0.0`).
- Work happens on `feat/<phase>-<name>`, `fix/<name>` or `chore/<name>`
  branches, merged into `develop` with `--no-ff`.
- Commits follow [Conventional Commits](https://www.conventionalcommits.org):
  `type(scope): imperative summary`, at most 72 characters in total.
    - Types: `feat`, `fix`, `refactor`, `perf`, `test`, `docs`, `style`,
      `build`, `ci`, `chore`, `revert`.
    - Scopes: `setup`, `db`, `auth`, `users`, `ui`, `tickets`, `assignment`,
      `escalation`, `tracking`, `notifications`, `reports`, `admin`,
      `security`, `ci`, `docs`.
- One logical change per commit, with its tests. The body explains why and
  lists requirement IDs, for example `Refs: REQ-2.3, TC04`.

## Continuous integration

GitHub Actions runs on every push and pull request to `main` and `develop`:
lint, static analysis, tests with coverage on MySQL 8.0, asset build, Dusk
end-to-end tests, and dependency audits.

## Security

See [SECURITY.md](SECURITY.md) for how to report a vulnerability.
