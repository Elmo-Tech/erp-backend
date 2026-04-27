<?php

namespace App\Modules\Report\Actions;

use App\Services\Sales\ExportService;
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
