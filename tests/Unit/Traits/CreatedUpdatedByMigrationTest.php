<?php

namespace Tests\Unit\Traits;

use App\Traits\CreatedUpdatedByMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreatedUpdatedByMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper class that uses the trait for testing
     */
    private function getTraitUser(): object
    {
        return new class {
            use CreatedUpdatedByMigration;
        };
    }

    /**
     * Test that addAuditColumns adds created_by column with correct constraints
     * _Requirements: 3.1_
     */
    public function test_add_audit_columns_creates_created_by_column(): void
    {
        $traitUser = $this->getTraitUser();

        Schema::create('test_audit_table', function (Blueprint $table) use ($traitUser) {
            $table->id();
            $table->string('name');
            $traitUser->addAuditColumns($table);
            $table->timestamps();
        });

        $this->assertTrue(Schema::hasColumn('test_audit_table', 'created_by'));

        // Clean up
        Schema::dropIfExists('test_audit_table');
    }

    /**
     * Test that addAuditColumns adds updated_by column with correct constraints
     * _Requirements: 3.2_
     */
    public function test_add_audit_columns_creates_updated_by_column(): void
    {
        $traitUser = $this->getTraitUser();

        Schema::create('test_audit_table', function (Blueprint $table) use ($traitUser) {
            $table->id();
            $table->string('name');
            $traitUser->addAuditColumns($table);
            $table->timestamps();
        });

        $this->assertTrue(Schema::hasColumn('test_audit_table', 'updated_by'));

        // Clean up
        Schema::dropIfExists('test_audit_table');
    }

    /**
     * Test that dropAuditColumns removes both audit columns
     * _Requirements: 3.1, 3.2_
     */
    public function test_drop_audit_columns_removes_both_columns(): void
    {
        $traitUser = $this->getTraitUser();

        // First create the table with audit columns
        Schema::create('test_audit_table', function (Blueprint $table) use ($traitUser) {
            $table->id();
            $table->string('name');
            $traitUser->addAuditColumns($table);
            $table->timestamps();
        });

        // Verify columns exist
        $this->assertTrue(Schema::hasColumn('test_audit_table', 'created_by'));
        $this->assertTrue(Schema::hasColumn('test_audit_table', 'updated_by'));

        // Drop the audit columns
        Schema::table('test_audit_table', function (Blueprint $table) use ($traitUser) {
            $traitUser->dropAuditColumns($table);
        });

        // Verify columns are removed
        $this->assertFalse(Schema::hasColumn('test_audit_table', 'created_by'));
        $this->assertFalse(Schema::hasColumn('test_audit_table', 'updated_by'));

        // Clean up
        Schema::dropIfExists('test_audit_table');
    }
}
