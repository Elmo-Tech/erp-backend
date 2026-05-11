<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dictionary table for invoice statuses.
 * Follows the same schema as all other dictionary tables (name/slug/is_active).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_statuses');
    }
};
