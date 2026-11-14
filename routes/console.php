<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Run `php artisan schedule:run` every minute (cron) or `php artisan schedule:work` while developing.
// withoutOverlapping stops a slow run from being started again; its lock expires after 10 minutes, so a
// crashed run cannot block escalation for longer than that.
Schedule::command('tickets:escalate-overdue')->everyFifteenMinutes()->withoutOverlapping(10);
