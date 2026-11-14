<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use Illuminate\Database\Seeder;

class SlaPolicySeeder extends Seeder
{
    /**
     * Existing policies are left alone so hours changed by an administrator survive a re-seed.
     */
    public function run(): void
    {
        $hours = [
            TicketPriority::High->value => 4,
            TicketPriority::Medium->value => 8,
            TicketPriority::Low->value => 24,
        ];

        foreach ($hours as $priority => $resolutionHours) {
            SlaPolicy::query()->firstOrCreate(
                ['priority' => $priority],
                ['resolution_hours' => $resolutionHours],
            );
        }
    }
}
