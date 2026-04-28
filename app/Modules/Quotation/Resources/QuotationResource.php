<?php

namespace App\Modules\Quotation\Resources;

use App\Modules\Lead\Resources\LeadResource;
use App\Modules\Sales\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pricingService = app(PricingService::class);

        // Convert estimations to camelCase
        $estimations = $this->estimations ?? [];
        $camelCaseEstimations = $pricingService->convertEstimationsToCamelCase($estimations);

        // Calculate pricing breakdown
        $pricingBreakdown = [];
        if (! empty($estimations) && $this->hourly_rate > 0) {
            try {
                $pricingBreakdown = $pricingService->calculateQuote(
                    $camelCaseEstimations,
                    (float) $this->hourly_rate,
                    (float) $this->discount_percentage
                );
            } catch (\Exception $e) {
                // If calculation fails, provide empty breakdown
                $pricingBreakdown = [
                    'totalHours' => 0.0,
                    'grossPrice' => 0.0,
                    'discountAmount' => 0.0,
                    'finalPrice' => 0.0,
                ];
            }
        }

        return [
            'quotationId' => $this->id,
            'leadId' => $this->lead_id ?? 0,
            'estimations' => $camelCaseEstimations,
            'hourlyRate' => (float) ($this->hourly_rate ?? 0.0),
            'discountPercentage' => (float) ($this->discount_percentage ?? 0.0),
            'finalPrice' => (float) ($this->final_price ?? 0.0),
            'currency' => $this->currency ?? '',
            'status' => $this->status?->value ?? '',
            'expiresAt' => $this->expires_at?->toISOString() ?? null,
            'paymentPlan' => $this->payment_plan ?? null,
            'rejectionReason' => $this->rejection_reason ?? null,
            'pricingBreakdown' => $pricingBreakdown,
            'paymentBreakdown' => $this->when($this->payment_plan, function () use ($pricingService) {
                try {
                    return $pricingService->calculatePaymentAmounts(
                        (float) $this->final_price,
                        $this->payment_plan
                    );
                } catch (\Exception $e) {
                    return null;
                }
            }),
            'lead' => $this->whenLoaded('lead', function () {
                return new LeadResource($this->lead);
            }),
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            'createdBy' => $this->created_by ?? 0,
            'updatedBy' => $this->updated_by ?? 0,
        ];
    }
}
