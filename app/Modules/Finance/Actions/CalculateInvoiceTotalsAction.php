<?php

namespace App\Modules\Finance\Actions;

/**
 * Enforces the invoice math contract on the backend:
 *
 *   subtotal        = SUM(item.quantity * item.unit_price)
 *   tax_amount      = subtotal * (tax_rate / 100)
 *   total_amount    = subtotal + tax_amount - discount_amount
 *
 * Returns a validated totals array ready to be merged into the invoice payload.
 * This action is intentionally stateless — it receives raw data and returns
 * computed values without touching the database.
 */
class CalculateInvoiceTotalsAction
{
    /**
     * @param  array<int, array{quantity: int, unit_price: float|string}>  $items
     * @param  float|string  $taxRate        Percentage, e.g. 15 means 15 %
     * @param  float|string  $discountAmount Flat discount in currency units
     * @return array{subtotal: float, tax_amount: float, total_amount: float}
     */
    public function execute(array $items, float|string $taxRate = 0, float|string $discountAmount = 0): array
    {
        $taxRate        = (float) $taxRate;
        $discountAmount = (float) $discountAmount;

        // 1. Subtotal — sum of all line items
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (int) $item['quantity'] * (float) $item['unit_price'];
        }

        // 2. Tax amount derived from subtotal
        $taxAmount = round($subtotal * ($taxRate / 100), 2);

        // 3. Total — subtotal + tax - discount (floor at 0)
        $totalAmount = max(0.0, round($subtotal + $taxAmount - $discountAmount, 2));

        return [
            'subtotal'     => round($subtotal, 2),
            'tax_amount'   => $taxAmount,
            'total_amount' => $totalAmount,
        ];
    }
}
