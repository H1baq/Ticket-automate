<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EscalationService;
use Illuminate\Console\Command;

class EscalateOverdueTickets extends Command
{
    protected $signature = 'tickets:escalate-overdue';

    protected $description = 'Escalate tickets that are unresolved past their due time';

    public function handle(EscalationService $escalations): int
    {
        $count = $escalations->escalateOverdue();

        $this->info($count === 0
            ? 'No overdue tickets to escalate.'
            : "Escalated {$count} overdue ".($count === 1 ? 'ticket' : 'tickets').'.');

        return self::SUCCESS;
    }
}
