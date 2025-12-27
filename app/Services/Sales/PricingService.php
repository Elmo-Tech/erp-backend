<?php

namespace App\Services\Sales;

use InvalidArgumentException;

class PricingService
{
    /**
     * Calculate quotation pricing from estimations.
     *
     * @param array $estimations Hour estimations in camelCase (frontendHours, backendHours, etc.)
     * @param float $hourlyRate Rate per hour
     * @param float $discountPercentage Discount percentage (0-100)
     * @return array Pricing breakdown with totalHours, grossPrice, discountAmount, finalPrice
     * @throws InvalidArgumentException
     */
    public function calculateQuote(array $estimations, float $hourlyRate, float $discountPercentage): array
    {
        // Validate hourly rate
        if ($hourlyRate <= 0) {
            throw new InvalidArgumentException('Hourly rate must be greater than zero.');
        }

        // Validate discount percentage
        if ($discountPercentage < 0 || $discountPercentage > 100) {
            throw new InvalidArgumentException('Discount percentage must be between 0 and 100.');
        }

        // Calculate total hours from estimations
        $totalHours = $this->calculateTotalHours($estimations);

        // Calculate gross price
        $grossPrice = $totalHours * $hourlyRate;

        // Calculate discount amount
        $discountAmount = $grossPrice * ($discountPercentage / 100);

        // Calculate final price
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
     *
     * @param array $estimations
     * @return float
     * @throws InvalidArgumentException
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
     * Convert camelCase estimations to snake_case for database storage.
     *
     * @param array $estimations
     * @return array
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
     *
     * @param array $estimations
     * @return array
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
}