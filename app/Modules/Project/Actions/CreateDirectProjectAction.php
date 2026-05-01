<?php

namespace App\Modules\Project\Actions;

use App\Modules\Client\Services\ClientActivityLogger;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Support\Facades\DB;

class CreateDirectProjectAction
{
    public function __construct(
        private CreateProjectAction $createProjectAction,
        private ClientActivityLogger $clientActivityLogger
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $user): Project
    {
        return DB::transaction(function () use ($data, $user) {
            $project = $this->createProjectAction->execute($data);
            $this->clientActivityLogger->projectCreated($project->load('client'), $user);

            return $project;
        });
    }
}
