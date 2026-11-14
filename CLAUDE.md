# HDTMS — Help Desk Ticketing and Management System

## What this is
A web-based IT help desk for the Kenya School of Government (KSG), Lower Kabete Campus.
Staff users report IT problems as tickets; support agents resolve them; administrators
assign tickets, manage the system and produce reports. It is a final-year project
(KCA University) that must match its approved proposal, SRS and SDS, and it should be
built well enough to become a commercial product later.

## The four objectives (everything we build serves one of these)
1. Ticket submission module — capture, categorize and prioritize requests; issue a
   unique ticket number (HD-YYYY-NNNNN) and a due time from the SLA policy.
2. Ticket assignment module — admin routes each ticket to a support agent; tickets
   unresolved past their priority's resolution hours are escalated automatically.
3. Ticket tracking module — every status change and resolution is recorded in the
   history; the requesting user is notified of each update (in-app + email).
4. Reporting module — reports on ticket volumes, resolution times and agent workload,
   exportable to PDF and CSV.
Supporting: authentication with roles (user, agent, admin) and system administration.

## Stack (fixed — do not substitute)
- PHP 8.3 (min 8.2), Laravel 12, MySQL 8.0 (MariaDB in local XAMPP is acceptable)
- Laravel Fortify (headless auth) with our own Blade views — NOT Breeze/Jetstream/Tailwind
- Blade + Bootstrap 5.3 compiled from Sass with our own theme, Vite, vanilla JS (ES modules)
- Chart.js for charts, barryvdh/laravel-dompdf for PDF export
- Database queue + Laravel scheduler; Mailpit/log mailer locally, SMTP in production
- Quality: Laravel Pint (PSR-12 preset), Larastan level 6, PHPUnit, Laravel Dusk (E2E),
  ESLint + Prettier (JS), Stylelint (SCSS)
- Do not add any other Composer or npm package without asking me first and explaining why.

## Architecture rules
- MVC with thin controllers. Business rules live in `app/Services` (TicketService,
  AssignmentService, EscalationService, ReportService). Controllers: validate → call
  service → return view/redirect. No queries or business logic in Blade views.
- Validation only in Form Request classes (`app/Http/Requests`). Never `$request->all()`.
- Authorization only through Policies (`TicketPolicy`, `UserPolicy`) and the `role`
  middleware. Check on the server for every action, even if the button is hidden.
- PHP backed enums in `app/Enums`: UserRole, TicketPriority, TicketStatus. The allowed
  status transitions are defined ONCE in TicketStatus (`canTransitionTo()`).
- Side effects (notifications, emails) go through Events + queued Listeners.
- Every ticket write and its `ticket_history` row happen in one DB transaction.
- Models: `$fillable` set explicitly, `$casts` for enums/dates, typed relations.
- `declare(strict_types=1);` in every PHP file; typed properties, parameters and returns.
- No hard-coded "KSG" or "Kenya School of Government" in code — branding (app name,
  organization name, logo path, primary colour) comes from config/settings and seeders,
  so the product can be rebranded and sold to other organizations later.
- Prevent N+1: `Model::preventLazyLoading(! app()->isProduction())`; eager-load relations.

## Database (7 application tables — match the SDS data dictionary exactly)
users, categories, sla_policies, tickets, responses, ticket_history, notifications.
Change the schema ONLY with new migrations. Records linked to tickets are deactivated
(is_active = false), never deleted.

## Security (OWASP Top 10 mindset)
- Passwords: bcrypt via Hash, min 8 chars. Lock account 15 min after 5 failed logins
  (failed_logins, locked_until). Session lifetime 30 min, secure + httpOnly + SameSite=Lax.
- CSRF on all forms; Blade `{{ }}` escaping only — never `{!! !!}` on user input.
- Eloquent / query builder bindings only — no raw SQL string concatenation.
- Uploads: one file per ticket, JPG/PNG/PDF, ≤5 MB, validated by MIME, renamed to a
  random name, stored on the private `local` disk, served through an authorized route.
- Security headers middleware: CSP (no inline scripts), X-Frame-Options DENY,
  X-Content-Type-Options nosniff, Referrer-Policy, HSTS in production.
- Rate limit login, password reset, ticket creation and response posting.
- Never log passwords, tokens or full request bodies. Never commit `.env` or secrets.
- Users see only their own tickets; agents only tickets assigned to them; admins all.

## Design rules — this must NOT look like a generic AI-generated website
It is a working tool used all day by ICT staff, in the spirit of Linear, Zendesk's agent
workspace or GitHub Issues: calm, dense, fast and obvious.
- NO: purple/blue gradients, glassmorphism, hero sections, giant headings, stock
  illustrations, emoji in UI text, "Welcome back 👋", floating blobs, drop shadows on
  everything, rounded-full pill buttons everywhere, lorem ipsum, fake testimonials.
- Palette (Sass tokens in `resources/scss/_tokens.scss`): ink #1B2430, muted #5B6675,
  border #DDE1E6, surface #F6F7F9, white #FFFFFF, primary deep green #0F5C4D (hover
  #0B4A3E), focus ring #3B82F6. Status: Open #2563EB, Assigned #7C3AED, In Progress
  #D97706, Resolved #059669, Closed #6B7280; Overdue/escalated #B91C1C.
  Status is always text + colour, never colour alone.
- Type: "Source Sans 3" for UI, "JetBrains Mono" for ticket numbers and IDs, self-hosted
  in `public/fonts` (no Google Fonts CDN). Base 15px, tabular numbers in tables.
- 8px spacing grid, radius 6px, 1px borders instead of heavy shadows, max content
  width 1280px, left sidebar navigation that shows only the user's role items.
- Every list has: an empty state with a useful action, pagination (20), filters that
  persist in the URL, and sortable headers where it helps.
- Every action gives feedback: toast on success, inline field errors, disabled button +
  spinner while submitting, confirmation modal for destructive actions.
- Keyboard: `n` new ticket, `/` focus search, `g d` dashboard; visible focus states.
- Accessibility: WCAG 2.2 AA contrast, labels on every input, aria-live for toasts,
  works from 360px wide upward.
- Write real, plain microcopy, e.g. "Ticket HD-2026-00142 created. We'll email you when
  an agent picks it up." — not "Success!".

## Git rules
- Branches: `main` (always releasable), `develop` (integration), and one branch per
  phase/feature: `feat/<phase>-<short-name>`, `fix/<short-name>`, `chore/<short-name>`.
- Conventional Commits, imperative mood, ≤72-char subject, lowercase type and scope:
  `feat(tickets): generate HD-YYYY-NNNNN numbers inside a locked transaction`
  Types: feat, fix, refactor, perf, test, docs, style, build, ci, chore, revert.
  Scopes: setup, db, auth, users, ui, tickets, assignment, escalation, tracking,
  notifications, reports, admin, security, ci, docs.
- One logical change per commit; tests in the same commit as the code they test.
  The body explains WHY and lists the requirement IDs, e.g. `Refs: REQ-2.3, TC04`.
- NEVER add "Co-Authored-By", "Generated with Claude Code" or any AI attribution to
  commits, PR titles or PR descriptions. The author is the repository owner only.
- Never commit with failing checks. Never `--no-verify`. Never force-push to main/develop.
- At the end of each phase: merge the feature branch into develop with `--no-ff`, and
  tag sprint milestones (`v0.1.0` … `v1.0.0`) on main.

## Definition of done (every phase)
`composer quality` passes (Pint test, Larastan, PHPUnit), `npm run lint` and
`npm run build` pass, new code has tests, the relevant TC test cases pass,
the CHANGELOG is updated, and you give me a short summary: what was built, which
REQ/TC IDs it covers, how to try it manually, and any decision I need to make.

## Commands
- `composer quality` — pint --test && phpstan analyse && php artisan test
- `npm run lint` / `npm run build`
- `php artisan test --filter=...`, `php artisan dusk`
- `php artisan schedule:work`, `php artisan queue:work`