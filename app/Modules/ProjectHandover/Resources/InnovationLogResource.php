<?php

namespace App\Modules\ProjectHandover\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InnovationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'logId' => $this->id,
            'userId' => $this->user_id,
            'userName' => $this->whenLoaded('user', fn () => $this->user->name, ''),
            'projectId' => $this->project_id ?? 0,
            'projectName' => $this->whenLoaded('project', fn () => $this->project?->name ?? '', ''),
            'taskId' => $this->task_id ?? 0,
            'taskTitle' => $this->whenLoaded('task', fn () => $this->task?->title ?? '', ''),
            'toolName' => $this->tool_name ?? '',
            'category' => $this->category ?? '',
            'categoryLabel' => $this->getCategoryLabel(),
            'description' => $this->description ?? '',
            'impactDescription' => $this->impact_description ?? '',
            'impactLevel' => $this->impact_level ?? '',
            'impactLevelLabel' => $this->getImpactLevelLabel(),
            'timeSavedMinutes' => (int) ($this->time_saved_minutes ?? 0),
            'isVerified' => (bool) $this->is_verified,
            'verifiedBy' => $this->verified_by ?? 0,
            'verifierName' => $this->whenLoaded('verifier', fn () => $this->verifier?->name ?? '', ''),
            'verifiedAt' => $this->verified_at?->toISOString() ?? '',
            'createdAt' => $this->created_at?->toISOString() ?? '',
        ];
    }

    private function getCategoryLabel(): string
    {
        return match ($this->category) {
            'ai_tool' => 'أداة ذكاء اصطناعي',
            'automation' => 'أتمتة',
            'new_tech' => 'تقنية جديدة',
            'process_improvement' => 'تحسين عملية',
            default => '',
        };
    }

    private function getImpactLevelLabel(): string
    {
        return match ($this->impact_level) {
            'low' => 'منخفض',
            'medium' => 'متوسط',
            'high' => 'عالي',
            default => '',
        };
    }
}
