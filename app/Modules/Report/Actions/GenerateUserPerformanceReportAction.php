<?php

namespace App\Modules\Report\Actions;

use App\Services\Reporting\UserPerformanceService;

class GenerateUserPerformanceReportAction
{
    public function __construct(
        private UserPerformanceService $userPerformanceService
    ) {}

    public function execute(array $filters = []): array
    {
        return $this->userPerformanceService->getAllUserPerformance($filters);
    }
}
