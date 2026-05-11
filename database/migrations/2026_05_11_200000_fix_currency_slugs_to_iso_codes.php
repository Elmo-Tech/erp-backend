<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BUG FIX: Currency slugs were auto-generated from the human-readable name
 * (e.g. "egyptian-pound") instead of the ISO 4217 code ("EGP").
 *
 * The frontend FormSelect/FormCombobox sends the slug as the form value, so
 * the backend was receiving "egyptian-pound" instead of "EGP".
 *
 * This migration corrects existing rows in the currencies table.
 */
return new class extends Migration
{
    private array $corrections = [
        'us-dollar'      => 'USD',
        'euro'           => 'EUR',
        'egyptian-pound' => 'EGP',
        'saudi-riyal'    => 'SAR',
        'uae-dirham'     => 'AED',
        'british-pound'  => 'GBP',
    ];

    public function up(): void
    {
        foreach ($this->corrections as $oldSlug => $newSlug) {
            DB::table('currencies')
                ->where('slug', $oldSlug)
                ->update(['slug' => $newSlug, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Reverse: ISO code → slugified name
        foreach ($this->corrections as $oldSlug => $newSlug) {
            DB::table('currencies')
                ->where('slug', $newSlug)
                ->update(['slug' => $oldSlug, 'updated_at' => now()]);
        }
    }
};
