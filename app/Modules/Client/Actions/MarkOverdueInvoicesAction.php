<?php

namespace App\Modules\Client\Actions;

use App\Enums\InvoiceStatus;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Notification\Notifications\InvoiceOverdue;
use Carbon\Carbon;

class MarkOverdueInvoicesAction
{
    public function execute(): int
    {
        $overdueInvoices = Invoice::withoutGlobalScopes()
            ->with(['project.client'])
            ->where('status', InvoiceStatus::SENT)
            ->where('due_date', '<', Carbon::today())
            ->get();

        $count = 0;
        foreach ($overdueInvoices as $invoice) {
            $invoice->update(['status' => InvoiceStatus::OVERDUE]);

            if ($invoice->project && $invoice->project->client?->user) {
                $invoice->project->client->user->notify(new InvoiceOverdue(
                    $invoice,
                    $invoice->due_date->diffInDays(Carbon::today())
                ));
            }

            $count++;
        }

        return $count;
    }
}
