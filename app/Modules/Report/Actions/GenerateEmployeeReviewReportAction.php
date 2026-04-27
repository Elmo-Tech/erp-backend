<?php

namespace App\Modules\Report\Actions;

use App\Services\Reporting\EmployeeReviewService;
use App\Modules\User\Models\User;

class GenerateEmployeeReviewReportAction
{
    public function __construct(
        private EmployeeReviewService $employeeReviewService
    ) {}

    public function execute(int $userId, array $filters = []): array
    {
        User::findOrFail($userId);

        return $this->employeeReviewService->getEmployeeReview($userId, $filters);
    }
}
