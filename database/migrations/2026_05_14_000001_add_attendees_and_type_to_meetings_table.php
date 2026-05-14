<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'meeting_type')) {
                $table->string('meeting_type')->nullable()->after('title');
            }

            if (! Schema::hasColumn('meetings', 'internal_attendees')) {
                $table->json('internal_attendees')->nullable()->after('assigned_to');
            }

            if (! Schema::hasColumn('meetings', 'external_attendees')) {
                $table->json('external_attendees')->nullable()->after('internal_attendees');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            foreach (['external_attendees', 'internal_attendees', 'meeting_type'] as $column) {
                if (Schema::hasColumn('meetings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
