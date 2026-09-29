<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class ErisDatabaseTestCase extends TestCase
{
    use RefreshDatabase;

    protected function shouldSeed(): bool
    {
        return false;
    }
}
