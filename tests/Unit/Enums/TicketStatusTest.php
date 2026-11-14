<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\TicketStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketStatusTest extends TestCase
{
    private const ALLOWED = [
        'assigned to in_progress' => ['assigned', 'in_progress'],
        'in_progress to resolved' => ['in_progress', 'resolved'],
        'resolved to closed' => ['resolved', 'closed'],
        'resolved to in_progress (reopen)' => ['resolved', 'in_progress'],
    ];

    /**
     * @return array<string, array{string, string}>
     */
    public static function allowedTransitions(): array
    {
        return self::ALLOWED;
    }

    /**
     * Every ordered pair of statuses that is not explicitly allowed.
     *
     * @return array<string, array{string, string}>
     */
    public static function forbiddenTransitions(): array
    {
        $allowed = array_values(self::ALLOWED);
        $pairs = [];

        foreach (TicketStatus::cases() as $from) {
            foreach (TicketStatus::cases() as $to) {
                if (! in_array([$from->value, $to->value], $allowed, true)) {
                    $pairs["{$from->value} to {$to->value}"] = [$from->value, $to->value];
                }
            }
        }

        return $pairs;
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transition_is_accepted(string $from, string $to): void
    {
        $this->assertTrue(TicketStatus::from($from)->canTransitionTo(TicketStatus::from($to)));
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_forbidden_transition_is_refused(string $from, string $to): void
    {
        $this->assertFalse(TicketStatus::from($from)->canTransitionTo(TicketStatus::from($to)));
    }

    public function test_the_provider_covers_all_twenty_five_pairs(): void
    {
        $this->assertCount(25, [...self::allowedTransitions(), ...self::forbiddenTransitions()]);
    }

    public function test_open_never_becomes_assigned_through_a_status_change(): void
    {
        $this->assertFalse(TicketStatus::Open->canTransitionTo(TicketStatus::Assigned));
    }

    public function test_closed_is_final(): void
    {
        foreach (TicketStatus::cases() as $status) {
            $this->assertFalse(TicketStatus::Closed->canTransitionTo($status));
        }
    }

    public function test_every_status_has_a_label_and_a_hex_colour(): void
    {
        foreach (TicketStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $status->colour());
        }
    }

    public function test_colours_follow_the_design_tokens(): void
    {
        $this->assertSame('#2563EB', TicketStatus::Open->colour());
        $this->assertSame('#7C3AED', TicketStatus::Assigned->colour());
        $this->assertSame('#D97706', TicketStatus::InProgress->colour());
        $this->assertSame('#059669', TicketStatus::Resolved->colour());
        $this->assertSame('#6B7280', TicketStatus::Closed->colour());
    }

    public function test_unresolved_and_finished_partition_all_statuses(): void
    {
        $all = [...TicketStatus::unresolved(), ...TicketStatus::finished()];

        $this->assertCount(count(TicketStatus::cases()), $all);
        $this->assertEqualsCanonicalizing(TicketStatus::cases(), $all);
    }
}
