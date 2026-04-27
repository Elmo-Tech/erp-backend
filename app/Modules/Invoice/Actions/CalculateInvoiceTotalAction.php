<?php

namespace App\Modules\Invoice\Actions;

class CalculateInvoiceTotalAction
{
    public function execute(float|int|string $amount): float
    {
        return round((float) $amount, 2);
    }
}
