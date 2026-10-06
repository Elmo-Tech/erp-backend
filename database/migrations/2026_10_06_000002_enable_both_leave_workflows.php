<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Client decision 5: leave workflow supports both employee requests
        // and direct Admin registration. Existing installs keep history by
        // inserting a new immutable policy version instead of updating.
        $latest = DB::table('hr_policies')->orderByDesc('effective_from')->orderByDesc('id')->first();

        if ($latest === null) {
            return;
        }

        $settings = json_decode((string) $latest->settings, true);

        if (! is_array($settings) || ($settings['leaveWorkflow'] ?? null) === 'both') {
            return;
        }

        $settings['leaveWorkflow'] = 'both';

        DB::table('hr_policies')->insert([
            'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
            'effective_from' => today()->toDateString(),
            'created_by' => null,
            'notes' => 'Client decision: enable both employee leave requests and direct Admin registration.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('hr_policies')
            ->where('notes', 'Client decision: enable both employee leave requests and direct Admin registration.')
            ->delete();
    }
};
