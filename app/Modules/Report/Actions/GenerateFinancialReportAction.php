<?php

namespace App\Modules\Report\Actions;

use App\Modules\Report\Services\ProfitabilityService;

class GenerateFinancialReportAction
{
    public function __construct(
        private ProfitabilityService $profitabilityService
    ) {}

    public function execute(array $filters = [], string $sortBy = 'profit', string $sortDirection = 'desc', int $perPage = 15): mixed
    {
        return $this->profitabilityService->getProfitabilityReport(
            $filters,
            $sortBy,
            $sortDirection,
            $perPage
        );
    }
}
