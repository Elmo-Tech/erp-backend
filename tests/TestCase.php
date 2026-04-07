<?php

namespace Tests;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function ensureClientProfile(User $user, array $attributes = []): Client
    {
        $client = $user->client;

        if ($client) {
            return $client;
        }

        $client = Client::factory()->create(array_merge([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], $attributes));

        $user->setRelation('client', $client);

        return $client;
    }
}
