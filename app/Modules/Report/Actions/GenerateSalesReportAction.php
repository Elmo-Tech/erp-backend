<?php

namespace App\Modules\Report\Actions;

use App\Modules\Sales\Services\ExportService;
use Carbon\Carbon;

class GenerateSalesReportAction
{
    public function __construct(
        private ExportService $exportService
    ) {}

    public function execute(Carbon $startDate, Carbon $endDate): array
    {
        return $this->exportService->generateSalesReport($startDate, $endDate);
    }
}
