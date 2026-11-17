<?php

namespace Tests\Feature;

use App\Domain\Auth\MfaService;
use App\Models\User;
use App\Models\WebUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_identity_has_no_mfa_columns_or_remember_token(): void
    {
        $user = User::factory()->create();
        $user->mfa_secret = (new MfaService())->makeSecret();
        $user->save();

        $web = WebUser::query()->whereKey($user->id)->firstOrFail();
        $this->assertSame(['id', 'name', 'email', 'password', 'is_active'],
            array_keys($web->getAttributes()));
        $this->assertArrayNotHasKey('mfa_secret', $web->getAttributes());
        $this->assertArrayNotHasKey('remember_token', $web->getAttributes());
        $this->assertTrue((new MfaService())->hasFactor($web));
    }
}
