<?php

namespace App\Modules\Task\Resources\Tasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="TagResource",
 *     type="object",
 *
 *     @OA\Property(property="tagId", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Backend"),
 *     @OA\Property(property="slug", type="string", example="backend"),
 *     @OA\Property(property="color", type="string", example="#10b981")
 * )
 */
class TagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tagId' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
        ];
    }
}
