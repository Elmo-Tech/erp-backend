<?php

namespace Tests\Unit\Client;

use App\Modules\Client\Actions\CreateClientAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateClientActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_legacy_client_payload_to_crm_columns(): void
    {
        $client = app(CreateClientAction::class)->execute([
            'name' => 'Acme Inc.',
            'email' => 'hello@acme.test',
            'phone' => '+20 100 000 0000',
            'company' => 'Acme Inc.',
            'country' => 'Egypt',
            'status' => 'active',
        ]);

        $this->assertSame('Acme Inc.', $client->display_name);
        $this->assertSame('hello@acme.test', $client->primary_email);
        $this->assertSame('+20 100 000 0000', $client->primary_phone);
        $this->assertSame('Acme Inc.', $client->company_name);
        $this->assertSame('client', $client->lifecycle_stage);
    }

    public function test_it_maps_add_client_payload_and_primary_contact(): void
    {
        $client = app(CreateClientAction::class)->execute([
            'type' => 'company',
            'displayName' => 'Acme Inc.',
            'primaryEmail' => 'hello@acme.test',
            'primaryPhone' => '+20 100 000 0000',
            'companyName' => 'Acme Inc.',
            'source' => 'website',
            'country' => 'Egypt',
            'status' => 'active',
            'interests' => ['web-development'],
            'contactPerson' => [
                'firstName' => 'Jane',
                'lastName' => 'Cooper',
                'displayName' => 'Jane Cooper',
                'email' => 'jane@acme.test',
                'phone' => '+20 100 000 0001',
                'isPrimary' => true,
            ],
        ]);

        $this->assertSame('Acme Inc.', $client->display_name);
        $this->assertSame('hello@acme.test', $client->primary_email);
        $this->assertSame(['interests' => ['web-development']], $client->metadata);
        $this->assertDatabaseHas('contacts', [
            'client_id' => $client->id,
            'display_name' => 'Jane Cooper',
            'email' => 'jane@acme.test',
            'is_primary' => true,
        ]);
    }
}
