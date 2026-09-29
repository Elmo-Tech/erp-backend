<?php

namespace Tests\Unit;

use Eris\TestTrait;
use Tests\ErisDatabaseTestCase;

class ErisDatabaseTestCaseTest extends ErisDatabaseTestCase
{
    use TestTrait;

    public function test_eris_seed_does_not_request_database_seeding(): void
    {
        $this->assertIsInt($this->seed);
        $this->assertFalse($this->shouldSeed());
        $this->assertFalse($this->migrateFreshUsing()['--seed']);
        $this->assertDatabaseCount('users', 0);
    }
}
