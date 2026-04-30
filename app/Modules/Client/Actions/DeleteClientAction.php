<?php

namespace App\Modules\Client\Actions;

use App\Modules\CRM\Models\Client;
use RuntimeException;

class DeleteClientAction
{
    public function execute(Client $client): void
    {
        if ($client->projects()->exists()) {
            throw new RuntimeException('Cannot delete client with existing projects');
        }

        $client->delete();
    }
}
