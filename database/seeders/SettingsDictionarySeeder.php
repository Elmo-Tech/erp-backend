<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettingsDictionarySeeder extends Seeder
{
    /**
     * Simple name-only dictionaries — slug is auto-generated via Str::slug().
     *
     * @var array<string, list<string>>
     */
    private array $dictionaries = [
        'lead_sources' => ['Website', 'Referral', 'LinkedIn', 'Trade Show'],
        'lead_interests' => ['Web Development', 'Mobile App', 'Branding', 'Support Retainer'],
        'meeting_types' => ['Discovery Call', 'Technical Review', 'Proposal Review', 'Kickoff'],
        'meeting_statuses' => ['Scheduled', 'Completed', 'Cancelled', 'No Show'],
        'proposal_statuses' => ['Draft', 'Sent', 'Approved', 'Rejected'],
        'quotation_types' => ['Fixed Price', 'Time and Materials', 'Retainer', 'Milestone Based'],
        'rejection_reasons' => ['Budget Mismatch', 'Timeline Conflict', 'Missing Requirements', 'Chose Competitor'],
        'skip_reasons' => ['Duplicate Lead', 'Insufficient Details', 'Outside Scope', 'Unresponsive'],
        'contract_models' => ['Fixed Scope', 'Monthly Retainer', 'Dedicated Team', 'Support Plan'],
        'billing_cycles' => ['One Time', 'Monthly', 'Quarterly', 'Annually'],
        'discount_types' => ['Percentage', 'Fixed Amount', 'Introductory', 'Loyalty'],
        'payment_statuses' => ['Pending', 'Paid', 'Overdue', 'Failed'],
        'project_types' => ['Implementation', 'Maintenance', 'Consulting', 'Internal'],
        'project_statuses' => ['Draft', 'Active', 'On Hold', 'Completed'],
        'countries' => ['Egypt', 'United States', 'Saudi Arabia', 'United Arab Emirates'],
        'client_statuses' => ['Prospect', 'Active', 'Inactive', 'Archived'],
        'contact_roles' => ['Decision Maker', 'Technical Contact', 'Finance Contact', 'Project Sponsor'],
        'invoice_statuses' => ['Draft', 'Sent', 'Paid', 'Overdue'],
    ];

    /**
     * Dictionaries where the slug must be an explicit value (not auto-generated).
     * Format: [ table => [ [name, slug], ... ] ]
     *
     * BUG FIX: Currency slugs MUST be ISO 4217 codes (e.g. "EGP", "USD") so
     * the frontend sends the correct currency code to the backend instead of
     * the human-readable label ("Egyptian Pound").
     *
     * @var array<string, list<array{0: string, 1: string}>>
     */
    private array $dictionariesWithSlug = [
        'currencies' => [
            ['US Dollar',      'USD'],
            ['Euro',           'EUR'],
            ['Egyptian Pound', 'EGP'],
            ['Saudi Riyal',    'SAR'],
            ['UAE Dirham',     'AED'],
            ['British Pound',  'GBP'],
        ],
    ];

    public function run(): void
    {
        // ── Auto-slug dictionaries ────────────────────────────────────────────
        foreach ($this->dictionaries as $table => $names) {
            foreach ($names as $name) {
                DB::table($table)->updateOrInsert(
                    ['slug' => Str::slug($name)],
                    [
                        'name'       => $name,
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        // ── Explicit-slug dictionaries ────────────────────────────────────────
        foreach ($this->dictionariesWithSlug as $table => $entries) {
            foreach ($entries as [$name, $slug]) {
                DB::table($table)->updateOrInsert(
                    ['slug' => $slug],
                    [
                        'name'       => $name,
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
