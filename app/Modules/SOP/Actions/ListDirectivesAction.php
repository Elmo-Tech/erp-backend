<?php

namespace App\Modules\SOP\Actions;

use App\Modules\SOP\Models\Directive;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListDirectivesAction
{
    public function execute(int $perPage = 15): LengthAwarePaginator
    {
        return Directive::query()
            ->latest()
            ->paginate($perPage);
    }
}
