<?php

namespace Tests\Feature\Project;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivatizeProjectFilesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dry_runs_then_moves_and_verifies_legacy_public_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $project = Project::factory()->create();
        $file = ProjectFile::create([
            'project_id' => $project->id,
            'name' => 'Legacy project file',
            'type' => 'file',
            'file_path' => "projects/{$project->id}/files/legacy.txt",
            'is_downloadable' => true,
        ]);
        Storage::disk('public')->put($file->file_path, 'legacy-private-content');

        $this->artisan('projects:privatize-files')
            ->expectsOutput('alreadyPrivate: 0')
            ->expectsOutput('pending: 1')
            ->expectsOutput('moved: 0')
            ->assertSuccessful();

        Storage::disk('public')->assertExists($file->file_path);
        Storage::disk('local')->assertMissing($file->file_path);

        $this->artisan('projects:privatize-files --execute')
            ->expectsOutput('moved: 1')
            ->assertSuccessful();

        Storage::disk('public')->assertMissing($file->file_path);
        Storage::disk('local')->assertExists($file->file_path, 'legacy-private-content');

        $this->artisan('projects:privatize-files')
            ->expectsOutput('alreadyPrivate: 1')
            ->expectsOutput('pending: 0')
            ->assertSuccessful();
    }

    public function test_command_fails_when_a_database_record_has_no_file_on_either_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $project = Project::factory()->create();
        ProjectFile::create([
            'project_id' => $project->id,
            'name' => 'Missing project file',
            'type' => 'file',
            'file_path' => "projects/{$project->id}/files/missing.txt",
            'is_downloadable' => true,
        ]);

        $this->artisan('projects:privatize-files')
            ->expectsOutput('missing: 1')
            ->assertFailed();
    }
}
