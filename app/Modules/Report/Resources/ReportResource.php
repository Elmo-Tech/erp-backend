<?php

namespace App\Modules\Report\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray($request): array
    {
        return is_array($this->resource) ? $this->resource : $this->resource->toArray();
    }
}
