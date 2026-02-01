<?php

namespace App\Services\Sales;

use App\Enums\CommissionType;
use InvalidArgumentException;

class PricingService
{
    /**
     * Calculate quotation pricing from items.
     *
     * @param array $items Quotation items [{resourceType, estimatedHours, hourlyRate, internalHourlyCost}]
     * @param float $discountPercentage Discount percentage (0-100)
     * @param string|null $commissionType Commission type (markup/margin)
     * @param float $commissionPercentage Commission percentage
     * @return array Pricing breakdown
     */
    public function calculateFromItems(
        array $items,
        float $discountPercentage = 0,
        ?string $commissionType = null,
        float $commissionPercentage = 0
    ): array {
        $this->validateDiscount($discountPercentage);
        $this->validateCommission($commissionPercentage);

        $subtotal = 0;
        $internalCost = 0;
        $totalHours = 0;
        $processedItems = [];

        foreach ($items as $item) {
            $hours = (float) ($item['estimatedHours'] ?? 0);
            $rate = (float) ($item['hourlyRate'] ?? 0);
            $internalRate = (float) ($item['internalHourlyCost'] ?? 0);

            if ($hours < 0 || $rate < 0) {
                throw new InvalidArgumentException('Hours and rate must be non-negative.');
            }

            $itemSubtotal = $hours * $rate;
            $itemInternalCost = $hours * $internalRate;

            $subtotal += $itemSubtotal;
            $internalCost += $itemInternalCost;
            $totalHours += $hours;

            $processedItems[] = [
                'resourceType' => $item['resourceType'] ?? 'other',
                'description' => $item['description'] ?? '',
                'estimatedHours' => $hours,
                'hourlyRate' => round($rate, 2),
                'internalHourlyCost' => round($internalRate, 2),
                'subtotal' => round($itemSubtotal, 2),
            ];
        }

        // Calculate discount
        $discountAmount = $subtotal * ($discountPercentage / 100);
        $afterDiscount = $subtotal - $discountAmount;

        // Calculate commission
        $commissionAmount = 0;
        $finalPrice = $afterDiscount;

        if ($commissionType && $commissionPercentage > 0) {
            if ($commissionType === CommissionType::MARKUP->value) {
                // Markup: add to price (client pays)
                $commissionAmount = $afterDiscount * ($commissionPercentage / 100);
                $finalPrice = $afterDiscount + $commissionAmount;
            } else {
                // Margin: deduct from profit (company pays)
                $commissionAmount = $afterDiscount * ($commissionPercentage / 100);
                // Final price stays the same, commission is internal
            }
        }

        // Calculate profit margin
        $profitMargin = $finalPrice - $internalCost;
        if ($commissionType === CommissionType::MARGIN->value) {
            $profitMargin -= $commissionAmount;
        }

        return [
            'items' => $processedItems,
            'totalHours' => round($totalHours, 2),
            'subtotal' => round($subtotal, 2),
            'discountPercentage' => round($discountPercentage, 2),
            'discountAmount' => round($discountAmount, 2),
            'afterDiscount' => round($afterDiscount, 2),
            'commissionType' => $commissionType,
            'commissionPercentage' => round($commissionPercentage, 2),
            'commissionAmount' => round($commissionAmount, 2),
            'finalPrice' => round($finalPrice, 2),
            'internalCost' => round($internalCost, 2),
            'profitMargin' => round($profitMargin, 2),
            'profitPercentage' => $finalPrice > 0 ? round(($profitMargin / $finalPrice) * 100, 2) : 0,
        ];
    }

    /**
     * Calculate quotation pricing from estimations (legacy support).
     *
     * @param array $estimations Hour estimations in camelCase (frontendHours, backendHours, etc.)
     * @param float $hourlyRate Rate per hour
     * @param float $discountPercentage Discount percentage (0-100)
     * @return array Pricing breakdown with totalHours, grossPrice, discountAmount, finalPrice
     * @throws InvalidArgumentException
     */
    public function calculateQuote(array $estimations, float $hourlyRate, float $discountPercentage): array
    {
        if ($hourlyRate <= 0) {
            throw new InvalidArgumentException('Hourly rate must be greater than zero.');
        }

        $this->validateDiscount($discountPercentage);

        $totalHours = $this->calculateTotalHours($estimations);
        $grossPrice = $totalHours * $hourlyRate;
        $discountAmount = $grossPrice * ($discountPercentage / 100);
        $finalPrice = $grossPrice - $discountAmount;

        return [
            'totalHours' => $totalHours,
            'grossPrice' => round($grossPrice, 2),
            'discountAmount' => round($discountAmount, 2),
            'finalPrice' => round($finalPrice, 2),
        ];
    }

    /**
     * Calculate total hours from estimations array.
     */
    private function calculateTotalHours(array $estimations): float
    {
        $totalHours = 0;

        foreach ($estimations as $key => $hours) {
            if (!is_numeric($hours)) {
                throw new InvalidArgumentException("Invalid hour value for {$key}.");
            }

            if ($hours < 0) {
                throw new InvalidArgumentException("Hour value for {$key} cannot be negative.");
            }

            $totalHours += (float) $hours;
        }

        return $totalHours;
    }

    /**
     * Validate discount percentage
     */
    private function validateDiscount(float $discountPercentage): void
    {
        if ($discountPercentage < 0 || $discountPercentage > 100) {
            throw new InvalidArgumentException('Discount percentage must be between 0 and 100.');
        }
    }

    /**
     * Validate commission percentage
     */
    private function validateCommission(float $commissionPercentage): void
    {
        if ($commissionPercentage < 0 || $commissionPercentage > 100) {
            throw new InvalidArgumentException('Commission percentage must be between 0 and 100.');
        }
    }

    /**
     * Convert camelCase estimations to snake_case for database storage.
     */
    public function convertEstimationsToSnakeCase(array $estimations): array
    {
        $converted = [];
        foreach ($estimations as $key => $value) {
            $snakeKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
            $converted[$snakeKey] = $value;
        }
        return $converted;
    }

    /**
     * Convert snake_case estimations to camelCase for API response.
     */
    public function convertEstimationsToCamelCase(array $estimations): array
    {
        $converted = [];
        foreach ($estimations as $key => $value) {
            $camelKey = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
            $converted[$camelKey] = $value;
        }
        return $converted;
    }

    /**
     * Validate payment plan structure and percentages.
     * 
     * @param array $paymentPlan Array of payment milestones with percentages
     * @return bool
     * @throws InvalidArgumentException
     */
    public function validatePaymentPlan(array $paymentPlan): bool
    {
        if (empty($paymentPlan)) {
            throw new InvalidArgumentException('Payment plan cannot be empty.');
        }

        $totalPercentage = 0;
        $milestones = [];

        foreach ($paymentPlan as $index => $milestone) {
            // Validate structure
            if (!is_array($milestone)) {
                throw new InvalidArgumentException("Payment plan item at index {$index} must be an array.");
            }

            if (!isset($milestone['milestone']) || !isset($milestone['percentage'])) {
                throw new InvalidArgumentException("Payment plan item at index {$index} must have 'milestone' and 'percentage' keys.");
            }

            // Validate milestone name
            $milestoneName = trim($milestone['milestone']);
            if (empty($milestoneName)) {
                throw new InvalidArgumentException("Milestone name at index {$index} cannot be empty.");
            }

            // Check for duplicate milestones
            if (in_array($milestoneName, $milestones)) {
                throw new InvalidArgumentException("Duplicate milestone '{$milestoneName}' found in payment plan.");
            }
            $milestones[] = $milestoneName;

            // Validate percentage
            $percentage = $milestone['percentage'];
            if (!is_numeric($percentage)) {
                throw new InvalidArgumentException("Percentage at index {$index} must be numeric.");
            }

            $percentage = (float) $percentage;
            if ($percentage <= 0 || $percentage > 100) {
                throw new InvalidArgumentException("Percentage at index {$index} must be between 0.01 and 100.");
            }

            $totalPercentage += $percentage;
        }

        // Validate total percentage equals 100%
        if (abs($totalPercentage - 100) > 0.01) { // Allow small floating point differences
            throw new InvalidArgumentException("Total payment plan percentage must equal 100%. Current total: {$totalPercentage}%");
        }

        return true;
    }

    /**
     * Calculate payment amounts based on final price and payment plan.
     * 
     * @param float $finalPrice Total quotation price
     * @param array $paymentPlan Validated payment plan
     * @return array Payment breakdown with amounts
     */
    public function calculatePaymentAmounts(float $finalPrice, array $paymentPlan): array
    {
        $this->validatePaymentPlan($paymentPlan);

        $payments = [];
        $totalCalculated = 0;

        foreach ($paymentPlan as $index => $milestone) {
            $percentage = (float) $milestone['percentage'];
            $amount = round(($finalPrice * $percentage) / 100, 2);
            
            $payments[] = [
                'milestone' => $milestone['milestone'],
                'percentage' => $percentage,
                'amount' => $amount,
                'currency' => 'USD', // Default currency, can be parameterized
            ];

            $totalCalculated += $amount;
        }

        // Adjust last payment for rounding differences
        if (count($payments) > 0 && abs($totalCalculated - $finalPrice) > 0.01) {
            $difference = $finalPrice - $totalCalculated;
            $payments[count($payments) - 1]['amount'] += $difference;
            $payments[count($payments) - 1]['amount'] = round($payments[count($payments) - 1]['amount'], 2);
        }

        return [
            'payments' => $payments,
            'totalAmount' => $finalPrice,
            'totalPercentage' => 100,
        ];
    }
}