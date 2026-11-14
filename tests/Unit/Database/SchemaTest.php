<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function tableColumns(): array
    {
        return [
            'users' => ['users', [
                'id', 'name', 'email', 'password', 'role', 'department', 'is_active',
                'failed_logins', 'locked_until', 'remember_token', 'created_at', 'updated_at',
            ]],
            'categories' => ['categories', [
                'id', 'name', 'description', 'is_active', 'created_at', 'updated_at',
            ]],
            'sla_policies' => ['sla_policies', [
                'id', 'priority', 'resolution_hours', 'created_at', 'updated_at',
            ]],
            'tickets' => ['tickets', [
                'id', 'ticket_no', 'requester_id', 'agent_id', 'category_id', 'title', 'description',
                'priority', 'status', 'attachment_path', 'due_at', 'escalation_level', 'escalated_at',
                'resolution', 'resolved_at', 'closed_at', 'created_at', 'updated_at',
            ]],
            'responses' => ['responses', [
                'id', 'ticket_id', 'user_id', 'message', 'is_internal', 'created_at', 'updated_at',
            ]],
            'ticket_history' => ['ticket_history', [
                'id', 'ticket_id', 'changed_by', 'action', 'old_status', 'new_status', 'details',
                'created_at',
            ]],
            'notifications' => ['notifications', [
                'id', 'user_id', 'ticket_id', 'message', 'channel', 'read_at', 'created_at',
            ]],
        ];
    }

    /**
     * @param  list<string>  $columns
     *
     */
    #[DataProvider('tableColumns')]
    public function test_table_has_exactly_the_data_dictionary_columns(string $table, array $columns): void
    {
        $actual = Schema::getColumnListing($table);

        $this->assertEqualsCanonicalizing($columns, $actual);
    }

    public function test_framework_tables_exist(): void
    {
        foreach (['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }
    }

    public function test_tickets_have_the_required_indexes(): void
    {
        $indexes = collect(Schema::getIndexes('tickets'))
            ->mapWithKeys(fn (array $index): array => [implode(',', $index['columns']) => $index]);

        $this->assertTrue($indexes['ticket_no']['unique']);
        $this->assertTrue($indexes->has('status,due_at'));
        $this->assertTrue($indexes->has('agent_id'));
        $this->assertTrue($indexes->has('requester_id'));
    }

    public function test_unique_constraints_exist(): void
    {
        $unique = fn (string $table, string $column): bool => collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === [$column]);

        $this->assertTrue($unique('users', 'email'));
        $this->assertTrue($unique('categories', 'name'));
        $this->assertTrue($unique('sla_policies', 'priority'));
    }
}
