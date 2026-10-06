<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $durations = [
            ['value' => '3', 'label' => '3 months', 'sort_order' => 1],
            ['value' => '6', 'label' => '6 months', 'sort_order' => 2],
            ['value' => '12', 'label' => '1 year', 'sort_order' => 3],
            ['value' => '24', 'label' => '2 years', 'sort_order' => 4],
        ];

        foreach ($durations as $duration) {
            $exists = DB::table('lookup_options')
                ->where('type', 'contract_duration')
                ->where('value', $duration['value'])
                ->exists();

            if (! $exists) {
                DB::table('lookup_options')->insert([
                    'type' => 'contract_duration',
                    'value' => $duration['value'],
                    'label' => $duration['label'],
                    'color' => null,
                    'icon' => null,
                    'metadata' => json_encode(['months' => (int) $duration['value'], 'is_system' => false]),
                    'sort_order' => $duration['sort_order'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('lookup_options')->where('type', 'contract_duration')->delete();
    }
};
