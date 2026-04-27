<?php

namespace Tests\Feature\ModuleArchitecture;

use App\Modules\User\Models\User;
use Tests\TestCase;

class UserEmployeeAuthModuleTest extends TestCase
{
    public function test_auth_user_and_employee_domains_are_loaded_from_modules(): void
    {
        $expectedClasses = [
            \App\Modules\Auth\Controllers\AuthController::class,
            \App\Modules\Auth\Requests\LoginRequest::class,
            \App\Modules\Auth\Actions\LoginUserAction::class,
            \App\Modules\Auth\Actions\GenerateTokenAction::class,
            \App\Modules\User\Controllers\UserController::class,
            \App\Modules\User\Controllers\AdminUserController::class,
            \App\Modules\User\Models\User::class,
            \App\Modules\User\Actions\CreateUserAction::class,
            \App\Modules\User\Resources\UserResource::class,
            \App\Modules\Employee\Controllers\EmployeeController::class,
            \App\Modules\Employee\Actions\CreateEmployeeAction::class,
            \App\Modules\Employee\Resources\EmployeeResource::class,
        ];

        foreach ($expectedClasses as $class) {
            $this->assertTrue(class_exists($class), "Expected {$class} to exist.");
        }

        $this->assertSame(User::class, config('auth.providers.users.model'));

        foreach (['auth.login', 'auth.profile', 'admin.users.index', 'employees.index'] as $routeName) {
            $this->assertTrue($this->app['router']->getRoutes()->hasNamedRoute($routeName));
        }
    }
}
