<?php

namespace App\Modules\Client\Actions;

use App\Modules\Client\Models\Client;

class CreateClientAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Client
    {
        return Client::create($data);
    }
}
