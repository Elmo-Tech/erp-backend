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

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_specialization_check');
            DB::statement('ALTER TABLE users ALTER COLUMN specialization TYPE VARCHAR(100)');
            DB::statement('ALTER TABLE users ALTER COLUMN specialization DROP DEFAULT');
        }
    }

    public function down(): void
    {
        //
    }
};
