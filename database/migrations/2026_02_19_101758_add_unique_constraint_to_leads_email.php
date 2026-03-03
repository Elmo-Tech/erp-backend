<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration adds a unique constraint to the leads.email column.
     * Before adding the constraint, it handles any duplicate emails by:
     * 1. Finding all duplicate emails
     * 2. Keeping the most recent lead for each email
     * 3. Appending a timestamp to older duplicate emails
     */
    public function up(): void
    {
        // Step 1: Handle duplicate emails before adding unique constraint
        $this->handleDuplicateEmails();
        
        // Step 2: Add unique constraint
        Schema::table('leads', function (Blueprint $table) {
            $table->unique('email', 'idx_leads_email_unique');
        });
    }

    /**
     * Handle duplicate emails by appending timestamp to older entries
     */
    private function handleDuplicateEmails(): void
    {
        // Find all duplicate emails
        $duplicates = DB::table('leads')
            ->select('email', DB::raw('COUNT(*) as email_count'))
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            // Get all leads with this email, ordered by created_at DESC (newest first)
            $leads = DB::table('leads')
                ->where('email', $duplicate->email)
                ->orderBy('created_at', 'desc')
                ->get();

            // Skip the first (newest) lead, update the rest
            foreach ($leads->skip(1) as $lead) {
                $timestamp = now()->timestamp;
                $newEmail = $lead->email . '_duplicate_' . $timestamp . '_' . $lead->id;
                
                DB::table('leads')
                    ->where('id', $lead->id)
                    ->update([
                        'email' => $newEmail,
                        'updated_at' => now()
                    ]);
                
                // Log this change in audit_logs if the table exists
                try {
                    DB::table('audit_logs')->insert([
                        'user_id' => null,
                        'event_type' => 'data_cleanup',
                        'auditable_type' => 'App\\Models\\Lead',
                        'auditable_id' => $lead->id,
                        'old_values' => json_encode(['email' => $lead->email]),
                        'new_values' => json_encode(['email' => $newEmail]),
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'Migration Script',
                        'severity' => 'info',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                } catch (\Exception $e) {
                    // Silently fail if audit_logs doesn't exist or has issues
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique('idx_leads_email_unique');
        });
    }
};
