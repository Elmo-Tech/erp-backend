<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['name' => 'Backend', 'slug' => 'backend', 'color' => '#10b981', 'type' => 'task'],
            ['name' => 'Frontend', 'slug' => 'frontend', 'color' => '#3b82f6', 'type' => 'task'],
            ['name' => 'DevOps', 'slug' => 'devops', 'color' => '#8b5cf6', 'type' => 'task'],
            ['name' => 'Testing', 'slug' => 'testing', 'color' => '#f59e0b', 'type' => 'task'],
            ['name' => 'UI/UX', 'slug' => 'ui-ux', 'color' => '#ec4899', 'type' => 'task'],
            ['name' => 'Database', 'slug' => 'database', 'color' => '#06b6d4', 'type' => 'task'],
            ['name' => 'API', 'slug' => 'api', 'color' => '#84cc16', 'type' => 'task'],
            ['name' => 'Bug Fix', 'slug' => 'bug-fix', 'color' => '#ef4444', 'type' => 'task'],
            ['name' => 'Feature', 'slug' => 'feature', 'color' => '#6366f1', 'type' => 'task'],
            ['name' => 'Documentation', 'slug' => 'documentation', 'color' => '#64748b', 'type' => 'task'],
            ['name' => 'Security', 'slug' => 'security', 'color' => '#dc2626', 'type' => 'task'],
            ['name' => 'Performance', 'slug' => 'performance', 'color' => '#0891b2', 'type' => 'task'],
            ['name' => 'Mobile', 'slug' => 'mobile', 'color' => '#8b5cf6', 'type' => 'task'],
            ['name' => 'Quotation', 'slug' => 'quotation', 'color' => '#10b981', 'type' => 'task'],
            ['name' => 'Meeting', 'slug' => 'meeting', 'color' => '#3b82f6', 'type' => 'task'],
            ['name' => 'Voice Call', 'slug' => 'voice-call', 'color' => '#f59e0b', 'type' => 'task'],
            ['name' => 'AI', 'slug' => 'ai', 'color' => '#ec4899', 'type' => 'task'],
            ['name' => 'Chatbot', 'slug' => 'chatbot', 'color' => '#06b6d4', 'type' => 'task'],
            ['name' => 'n8n', 'slug' => 'n8n', 'color' => '#84cc16', 'type' => 'task'],
            ['name' => 'Workflows', 'slug' => 'workflows', 'color' => '#6366f1', 'type' => 'task'],
        ];

        foreach ($tags as $tag) {
            Tag::firstOrCreate(['slug' => $tag['slug']], $tag);
        }
    }
}
