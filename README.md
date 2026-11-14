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
    php artisan serve
    ```

    Open <http://localhost:8000>. For frontend work, run `npm run dev` in a
    second terminal instead of `npm run build`.

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
| `php artisan serve`                 | Start the app on port 8000                             |

### Browser tests

Dusk drives a real Chrome against a running server. Start the app, then run
the tests in a second terminal:

```bash
php artisan serve
php artisan dusk
```

If Chrome updates and the driver no longer matches, run
`php artisan dusk:chrome-driver --detect`.

### Code coverage

The static PHP installed by php.new has no coverage driver (Xdebug or PCOV),
so `composer test` runs without coverage on a Mac. Coverage is measured in
CI, which fails the build when line coverage of `app/Services` falls below
80%.

## Scheduler and queue

Notifications and emails are queued, and escalation and auto-close run on the
scheduler. Both need to be running for the full workflow:

```bash
php artisan queue:work       # processes queued notifications and emails
php artisan schedule:work    # runs scheduled tasks every minute (local use)
```

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
