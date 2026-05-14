<?php

namespace App\Modules\Dashboard\Actions;

use App\Enums\InvoiceStatus;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Models\Project;
use Carbon\Carbon;

class GetFinancialStatsAction
{
    public function execute(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        $invoiceStats = Invoice::withoutGlobalScopes()
            ->selectRaw(
                'COUNT(*) as total_invoices,
                COALESCE(SUM(amount), 0) as invoiced_total,
                COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as paid_total,
                COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as overdue_total,
                COALESCE(SUM(CASE WHEN status = ? AND paid_at >= ? THEN amount ELSE 0 END), 0) as paid_this_month',
                [
                    InvoiceStatus::PAID->value,
                    InvoiceStatus::OVERDUE->value,
                    InvoiceStatus::PAID->value,
                    $startOfMonth,
                ]
            )
            ->first();

        $projectBudget = Project::withoutGlobalScopes()
            ->selectRaw('COUNT(*) as total_projects, COALESCE(SUM(budget), 0) as total_budget')
            ->first();

        return [
            'total_invoices' => (int) $invoiceStats->total_invoices,
            'total_projects' => (int) $projectBudget->total_projects,
            'invoiced_total' => round((float) $invoiceStats->invoiced_total, 2),
            'paid_total' => round((float) $invoiceStats->paid_total, 2),
            'overdue_total' => round((float) $invoiceStats->overdue_total, 2),
            'paid_this_month' => round((float) $invoiceStats->paid_this_month, 2),
            'project_budget_total' => round((float) $projectBudget->total_budget, 2),
        ];
    }
}
