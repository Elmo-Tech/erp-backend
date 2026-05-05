<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Models\User;
use Database\Seeders\SettingsDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DictionaryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_it_manages_dictionary_records_for_a_dynamic_entity(): void
    {
        $createResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/settings/lead-sources', [
                'name' => 'Website Referral',
            ]);

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Website Referral')
            ->assertJsonPath('data.slug', 'website-referral')
            ->assertJsonPath('data.isActive', true);

        $id = $createResponse->json('data.id');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/settings/lead-sources')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.name', 'Website Referral');

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/settings/lead-sources/{$id}", [
                'name' => 'Partner Referral',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Partner Referral')
            ->assertJsonPath('data.slug', 'partner-referral')
            ->assertJsonPath('data.isActive', false);

        Permission::findOrCreate('delete_settings', 'web');
        $this->user->givePermissionTo('delete_settings');

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/settings/lead-sources/{$id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('lead_sources', [
            'id' => $id,
        ]);
    }

    public function test_it_rejects_unknown_dictionary_entities(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/settings/not-a-dictionary')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_settings_dictionary_seeder_populates_lookup_tables(): void
    {
        $this->seed(SettingsDictionarySeeder::class);

        $this->assertDatabaseHas('lead_sources', [
            'name' => 'Website',
            'slug' => 'website',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('currencies', [
            'name' => 'US Dollar',
            'slug' => 'us-dollar',
            'is_active' => true,
        ]);
    }
}
