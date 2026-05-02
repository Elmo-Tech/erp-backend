<?php

namespace Tests\Unit\Client;

use App\Modules\Client\Actions\CreateClientAction;
use App\Modules\Client\Actions\UpdateClientAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateClientActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_edit_client_payload_to_crm_columns(): void
    {
        $client = app(CreateClientAction::class)->execute([
            'type' => 'company',
            'displayName' => 'Acme Inc.',
            'primaryEmail' => 'hello@acme.test',
            'country' => 'Egypt',
            'status' => 'active',
        ]);

        $updatedClient = app(UpdateClientAction::class)->execute($client, [
            'type' => 'company',
            'displayName' => 'Acme Studio',
            'primaryEmail' => 'team@acme.test',
            'primaryPhone' => '+20 100 000 0002',
            'companyName' => 'Acme Studio',
            'country' => 'Egypt',
            'status' => 'inactive',
            'source' => 'referral',
            'interests' => ['branding'],
        ]);

        $this->assertSame('Acme Studio', $updatedClient->display_name);
        $this->assertSame('team@acme.test', $updatedClient->primary_email);
        $this->assertSame('+20 100 000 0002', $updatedClient->primary_phone);
        $this->assertSame('Acme Studio', $updatedClient->company_name);
        $this->assertSame('referral', $updatedClient->source);
        $this->assertSame('inactive', $updatedClient->status->value);
        $this->assertSame(['interests' => ['branding']], $updatedClient->metadata);
    }
}
