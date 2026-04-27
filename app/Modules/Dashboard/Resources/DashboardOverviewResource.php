<?php

namespace App\Modules\Dashboard\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DashboardOverviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return is_array($this->resource) ? $this->resource : $this->resource->toArray();
    }
}
