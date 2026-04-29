<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'lead_sources',
        'lead_interests',
        'meeting_types',
        'meeting_statuses',
        'proposal_statuses',
        'quotation_types',
        'rejection_reasons',
        'skip_reasons',
        'contract_models',
        'billing_cycles',
        'currencies',
        'discount_types',
        'payment_statuses',
        'project_types',
        'project_statuses',
        'countries',
        'client_statuses',
        'contact_roles',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                continue;
            }

            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
