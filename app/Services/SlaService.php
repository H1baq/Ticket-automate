<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The resolution time promised for each priority.
 *
 * Every transaction that touches these rows takes their locks in id order, ticket creation and this
 * service alike. That one shared order is what keeps saving the settings from deadlocking with people
 * who are reporting tickets at the same moment.
 */
class SlaService
{
    /**
     * Hours for each priority, keyed by priority value (high, medium, low).
     *
     * @return array<string, int>
     */
    public function hoursByPriority(): array
    {
        return SlaPolicy::query()->orderBy('id')->get()
            ->mapWithKeys(fn (SlaPolicy $policy): array => [$policy->priority->value => $policy->resolution_hours])
            ->all();
    }

    /**
     * Save new resolution times. They apply to tickets reported afterwards; every ticket already
     * reported keeps the due time it was given.
     *
     * @param  array<string, int>  $hours  Keyed by priority value. A priority that is left out keeps its time.
     * @return array<string, array{from: int|null, to: int}> What actually changed.
     */
    public function update(array $hours, User $changedBy): array
    {
        $changes = DB::transaction(function () use ($hours): array {
            // Lock every row in id order first, exactly as ticket creation does.
            $policies = SlaPolicy::query()->orderBy('id')->lockForUpdate()->get()->keyBy(fn (SlaPolicy $policy): string => $policy->priority->value);
            $changes = [];

            foreach ($policies as $priority => $policy) {
                if (isset($hours[$priority]) && $policy->resolution_hours !== $hours[$priority]) {
                    $changes[$priority] = ['from' => $policy->resolution_hours, 'to' => $hours[$priority]];
                    $policy->resolution_hours = $hours[$priority];
                    $policy->save();
                }
            }

            // A priority without a row would make ticket creation fail, so it is created rather than left missing.
            foreach (TicketPriority::cases() as $priority) {
                if (! $policies->has($priority->value) && isset($hours[$priority->value])) {
                    SlaPolicy::query()->create(['priority' => $priority, 'resolution_hours' => $hours[$priority->value]]);
                    $changes[$priority->value] = ['from' => null, 'to' => $hours[$priority->value]];
                }
            }

            return $changes;
        }, 5);

        if ($changes !== []) {
            Log::info('SLA resolution times changed.', ['changed_by' => $changedBy->id, 'changes' => $changes]);
        }

        return $changes;
    }
}
