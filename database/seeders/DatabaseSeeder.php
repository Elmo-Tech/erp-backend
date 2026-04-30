<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsDictionarySeeder::class,
            DemoUserSeeder::class,
            // TagSeeder::class,          // TODO: Requires App\Modules\Task\Models\Tag
            // DashboardDemoSeeder::class, // TODO: Requires Task, Invoice, SOP, TimeEntry modules
        ]);
    }
}
