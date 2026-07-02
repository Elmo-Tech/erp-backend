<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE users MODIFY specialization VARCHAR(100) NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        //
    }
};
