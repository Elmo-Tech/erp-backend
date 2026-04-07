<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy databases contain indexes on columns that no longer exist.
     * SQLite table rebuilds fail while trying to recreate them, so drop them
     * before any later migration touches those tables.
     *
     * @var array<string, array<int, string>>
     */
    private array $invalidIndexes = [
        'activities' => [
            'activities_user_id_index',
            'idx_activities_due_date',
            'idx_activities_related_to',
        ],
        'clients' => [
            'clients_industry_index',
        ],
        'contracts' => [
            'contracts_client_id_index',
            'idx_contracts_client',
        ],
        'invoices' => [
            'idx_invoices_client_status',
            'idx_invoices_issue_date',
            'invoices_client_id_index',
        ],
        'leads' => [
            'idx_leads_follow_up_date',
            'idx_leads_temperature',
            'leads_assigned_to_index',
        ],
        'point_transactions' => [
            'idx_point_transactions_reference',
        ],
        'proposals' => [
            'proposals_client_id_index',
        ],
        'quotations' => [
            'quotations_client_id_index',
            'quotations_valid_until_index',
        ],
        'task_deliverables' => [
            'task_deliverables_submitted_by_index',
        ],
        'time_entries' => [
            'time_entries_date_index',
            'time_entries_project_id_index',
            'time_entries_task_id_index',
        ],
    ];

    public function up(): void
    {
        foreach ($this->invalidIndexes as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $indexName) {
                $this->dropIndexIfExists($tableName, $indexName);
            }
        }
    }

    public function down(): void
    {
        // These indexes target columns that do not exist and should not return.
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! $this->indexExists($tableName, $indexName)) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement(sprintf('DROP INDEX "%s"', str_replace('"', '""', $indexName)));

            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        foreach (Schema::getConnection()->getSchemaBuilder()->getIndexes($tableName) as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
};
