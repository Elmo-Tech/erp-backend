<?php

namespace Tests\Unit\Traits;

use App\Modules\Client\Models\Client;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class HasCreatedUpdatedByTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that created_by is automatically set when creating a model
     * _Requirements: 1.5_
     */
    public function test_created_by_is_set_automatically_on_create(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $client = Client::create([
            'type' => 'company',
            'display_name' => 'Test Company',
            'primary_email' => 'test@example.com',
            'status' => 'active',
        ]);

        $this->assertEquals($user->id, $client->created_by);
        $this->assertEquals($user->id, $client->updated_by);
    }

    /**
     * Test that updated_by is automatically set when updating a model
     * _Requirements: 1.5_
     */
    public function test_updated_by_is_set_automatically_on_update(): void
    {
        $creator = User::factory()->create();
        $updater = User::factory()->create();

        Auth::login($creator);
        $client = Client::create([
            'type' => 'company',
            'display_name' => 'Test Company',
            'primary_email' => 'test-update@example.com',
            'status' => 'active',
        ]);

        $this->assertEquals($creator->id, $client->created_by);
        $this->assertEquals($creator->id, $client->updated_by);

        Auth::login($updater);
        $client->update(['display_name' => 'Updated Company']);

        $client->refresh();
        $this->assertEquals($creator->id, $client->created_by);
        $this->assertEquals($updater->id, $client->updated_by);
    }

    /**
     * Test that audit fields are not set when user is not authenticated
     * _Requirements: 1.5_
     */
    public function test_audit_fields_not_set_when_not_authenticated(): void
    {
        Auth::logout();

        $client = Client::create([
            'type' => 'company',
            'display_name' => 'Unauth Company',
            'primary_email' => 'unauth@example.com',
            'status' => 'active',
        ]);

        $this->assertNull($client->created_by);
        $this->assertNull($client->updated_by);
    }

    /**
     * Test that createdBy relationship works correctly
     * _Requirements: 1.5_
     */
    public function test_created_by_relationship(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $client = Client::create([
            'type' => 'company',
            'display_name' => 'Relation Company',
            'primary_email' => 'relation@example.com',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(User::class, $client->createdBy);
        $this->assertEquals($user->id, $client->createdBy->id);
        $this->assertEquals($user->name, $client->createdBy->name);
    }

    /**
     * Test that updatedBy relationship works correctly
     * _Requirements: 1.5_
     */
    public function test_updated_by_relationship(): void
    {
        $creator = User::factory()->create();
        $updater = User::factory()->create();

        Auth::login($creator);
        $client = Client::create([
            'type' => 'company',
            'display_name' => 'Updater Company',
            'primary_email' => 'updater@example.com',
            'status' => 'active',
        ]);

        Auth::login($updater);
        $client->update(['display_name' => 'Updater Company v2']);

        $client->refresh();
        $this->assertInstanceOf(User::class, $client->createdBy);
        $this->assertInstanceOf(User::class, $client->updatedBy);
        $this->assertEquals($creator->id, $client->createdBy->id);
        $this->assertEquals($updater->id, $client->updatedBy->id);
    }
}
