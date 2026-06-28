<?php

namespace Database\Seeders;

use App\Enums\TaskCategory;
use App\Enums\UserSpecialization;
use App\Models\LookupOption;
use Illuminate\Database\Seeder;

class LookupOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed User Specializations
        $specializationOrder = 1;
        foreach (UserSpecialization::cases() as $specialization) {
            LookupOption::updateOrCreate(
                [
                    'type' => 'specialization',
                    'value' => $specialization->value,
                ],
                [
                    'label' => $specialization->label(),
                    'color' => $specialization->color(),
                    'metadata' => [
                        'compatible_task_types' => $specialization->getCompatibleTaskTypes(),
                    ],
                    'sort_order' => $specializationOrder++,
                    'is_active' => true,
                ]
            );
        }

        // Seed Task Categories
        $categoryOrder = 1;
        foreach (TaskCategory::cases() as $category) {
            LookupOption::updateOrCreate(
                [
                    'type' => 'task_category',
                    'value' => $category->value,
                ],
                [
                    'label' => $category->label(),
                    'color' => $category->color(),
                    'metadata' => [],
                    'sort_order' => $categoryOrder++,
                    'is_active' => true,
                ]
            );
        }
    }
}
