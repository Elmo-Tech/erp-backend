<?php

namespace App\Modules\Client\Actions;

use App\Modules\Client\Models\Client;

class UpdateClientAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Client $client, array $data): Client
    {
        $client->update($data);

        return $client->refresh();
    }
}
