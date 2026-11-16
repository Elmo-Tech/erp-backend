<?php

namespace Tests\Feature;

use Tests\TestCase;

final class HealthTest extends TestCase
{
    public function test_api_health_is_public_and_has_no_sensitive_details(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'elmo-erp-api',
                'version' => 'v2-foundation',
            ]);
    }
}

