<?php

namespace Tests\Unit\Project;

use App\Modules\Client\Models\Client;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Resources\ProjectResource;
use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProjectResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_loaded_client_fields_from_client_database_columns(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create([
            'display_name' => 'Acme Display',
            'company_name' => 'Acme Company',
            'primary_email' => 'ops@acme.test',
            'primary_phone' => '+20 100 000 0000',
        ]);

        $project = Project::factory()
            ->for($client)
            ->create()
            ->load('client');

        $request = Request::create('/api/v1/projects/'.$project->id);
        $request->setUserResolver(fn () => $user);

        $data = (new ProjectResource($project))->toArray($request);

        $this->assertSame([
            'clientId' => $client->id,
            'name' => 'Acme Display',
            'email' => 'ops@acme.test',
            'company' => 'Acme Company',
            'phone' => '+20 100 000 0000',
        ], $data['client']);
    }
}
