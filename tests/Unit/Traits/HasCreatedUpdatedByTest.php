<?php

namespace Tests\Unit\Traits;

use App\Models\Lead;
use App\Modules\User\Models\User;
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
        // Create a user and authenticate
        $user = User::factory()->create();
        Auth::login($user);

        // Create a lead
        $lead = Lead::create([
            'name' => 'Test Lead',
            'email' => 'test@example.com',
            'company' => 'Test Company',
            'source' => 'website',
            'status' => 'new',
        ]);

        // Assert that created_by and updated_by are set to the authenticated user
        $this->assertEquals($user->id, $lead->created_by);
        $this->assertEquals($user->id, $lead->updated_by);
    }

    /**
     * Test that updated_by is automatically set when updating a model
     * _Requirements: 1.5_
     */
    public function test_updated_by_is_set_automatically_on_update(): void
    {
        // Create two users
        $creator = User::factory()->create();
        $updater = User::factory()->create();

        // Create a lead as the first user
        Auth::login($creator);
        $lead = Lead::create([
            'name' => 'Test Lead',
            'email' => 'test@example.com',
            'company' => 'Test Company',
            'source' => 'website',
            'status' => 'new',
        ]);

        // Verify initial state
        $this->assertEquals($creator->id, $lead->created_by);
        $this->assertEquals($creator->id, $lead->updated_by);

        // Update the lead as the second user
        Auth::login($updater);
        $lead->update(['name' => 'Updated Lead']);

        // Verify that created_by remains unchanged but updated_by is updated
        $lead->refresh();
        $this->assertEquals($creator->id, $lead->created_by);
        $this->assertEquals($updater->id, $lead->updated_by);
    }

    /**
     * Test that audit fields are not set when user is not authenticated
     * _Requirements: 1.5_
     */
    public function test_audit_fields_not_set_when_not_authenticated(): void
    {
        // Ensure no user is authenticated
        Auth::logout();

        // Create a lead
        $lead = Lead::create([
            'name' => 'Test Lead',
            'email' => 'test@example.com',
            'company' => 'Test Company',
            'source' => 'website',
            'status' => 'new',
        ]);

        // Assert that created_by and updated_by are null
        $this->assertNull($lead->created_by);
        $this->assertNull($lead->updated_by);
    }

    /**
     * Test that createdBy relationship works correctly
     * _Requirements: 1.5_
     */
    public function test_created_by_relationship(): void
    {
        // Create a user and authenticate
        $user = User::factory()->create();
        Auth::login($user);

        // Create a lead
        $lead = Lead::create([
            'name' => 'Test Lead',
            'email' => 'test@example.com',
            'company' => 'Test Company',
            'source' => 'website',
            'status' => 'new',
        ]);

        // Test the relationship
        $this->assertInstanceOf(User::class, $lead->createdBy);
        $this->assertEquals($user->id, $lead->createdBy->id);
        $this->assertEquals($user->name, $lead->createdBy->name);
    }

    /**
     * Test that updatedBy relationship works correctly
     * _Requirements: 1.5_
     */
    public function test_updated_by_relationship(): void
    {
        // Create two users
        $creator = User::factory()->create();
        $updater = User::factory()->create();

        // Create a lead as the first user
        Auth::login($creator);
        $lead = Lead::create([
            'name' => 'Test Lead',
            'email' => 'test@example.com',
            'company' => 'Test Company',
            'source' => 'website',
            'status' => 'new',
        ]);

        // Update the lead as the second user
        Auth::login($updater);
        $lead->update(['name' => 'Updated Lead']);

        // Test the relationships
        $lead->refresh();
        $this->assertInstanceOf(User::class, $lead->createdBy);
        $this->assertInstanceOf(User::class, $lead->updatedBy);
        $this->assertEquals($creator->id, $lead->createdBy->id);
        $this->assertEquals($updater->id, $lead->updatedBy->id);
    }
}
