<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['name' => 'Documentation', 'slug' => 'documentation', 'color' => '#64748b', 'type' => 'task'],
            ['name' => 'Design', 'slug' => 'design', 'color' => '#3b82f6', 'type' => 'task'],
            ['name' => 'Operations', 'slug' => 'operations', 'color' => '#10b981', 'type' => 'task'],
            ['name' => 'Review', 'slug' => 'review', 'color' => '#8b5cf6', 'type' => 'task'],
            ['name' => 'Urgent', 'slug' => 'urgent', 'color' => '#ef4444', 'type' => 'task'],
            ['name' => 'Blocked', 'slug' => 'blocked', 'color' => '#f97316', 'type' => 'task'],
            ['name' => 'Client Request', 'slug' => 'client-request', 'color' => '#06b6d4', 'type' => 'task'],
            ['name' => 'Follow Up', 'slug' => 'follow-up', 'color' => '#f59e0b', 'type' => 'task'],
            ['name' => 'Maintenance', 'slug' => 'maintenance', 'color' => '#84cc16', 'type' => 'task'],
            ['name' => 'Delivery', 'slug' => 'delivery', 'color' => '#6366f1', 'type' => 'task'],
        ];

        foreach ($tags as $tag) {
            Tag::updateOrCreate(['slug' => $tag['slug']], $tag);
        }
    }
}
