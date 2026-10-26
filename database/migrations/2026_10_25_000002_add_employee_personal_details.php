<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('job_title')->nullable();
            $table->string('work_arrangement', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->date('appointment_date')->nullable();
            $table->string('residence', 500)->nullable();
            $table->string('national_id', 30)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('other_phone', 50)->nullable();
            $table->string('education', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'job_title', 'work_arrangement', 'birth_date', 'appointment_date', 'residence',
            'national_id', 'mobile', 'other_phone', 'education',
        ]));
    }
};
