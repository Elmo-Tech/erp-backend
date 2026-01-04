<?php

namespace App\Services\Sales;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class QuotationService
{
    public function __construct(
        private PricingService $pricingService
    ) {}

    /**
     * Get paginated quotations with filters
     */
    public function getQuotations(array $filters = []): LengthAwarePaginator
    {
        return QueryBuilder::for(Quotation::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('lead_id'),
                AllowedFilter::exact('commission_user_id'),
                AllowedFilter::scope('expiring_in', 'expiringIn'),
                'search' => AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('quotation_number', 'like', "%{$value}%")
                            ->orWhereHas('lead', function ($q) use ($value) {
                                $q->where('name', 'like', "%{$value}%")
                                    ->orWhere('company', 'like', "%{$value}%");
                            });
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'final_price', 'expiry_date', 'sent_at'])
            ->allowedIncludes(['lead', 'items', 'template', 'commissionUser', 'contract'])
            ->defaultSort('-created_at')
            ->paginate($filters['perPage'] ?? 15);
    }

    /**
     * Create a new quotation with items
     */
    public function createQuotation(array $data, User $user): Quotation
    {
        return DB::transaction(function () use ($data, $user) {
            // Calculate pricing from items
            $pricing = $this->pricingService->calculateFromItems(
                $data['items'] ?? [],
                $data['discountPercentage'] ?? 0,
                $data['commissionType'] ?? null,
                $data['commissionPercentage'] ?? 0
            );

            // Create quotation
            $quotation = Quotation::create([
                'quotation_number' => Quotation::generateQuotationNumber(),
                'lead_id' => $data['leadId'],
                'template_id' => $data['templateId'] ?? null,
                'estimations' => $data['estimations'] ?? null, // Legacy support
                'hourly_rate' => $data['hourlyRate'] ?? 0,
                'discount_percentage' => $pricing['discountPercentage'],
                'final_price' => $data['overridePrice'] ?? $pricing['finalPrice'],
                'currency' => $data['currency'] ?? 'SAR',
                'status' => QuotationStatus::DRAFT,
                'expiry_date' => $data['expiryDate'] ?? null,
                'commission_type' => $pricing['commissionType'],
                'commission_percentage' => $pricing['commissionPercentage'],
                'commission_amount' => $pricing['commissionAmount'],
                'commission_user_id' => $data['commissionUserId'] ?? null,
                'internal_cost' => $pricing['internalCost'],
                'profit_margin' => $pricing['profitMargin'],
                'override_price' => $data['overridePrice'] ?? null,
                'override_reason' => $data['overrideReason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms_conditions' => $data['termsConditions'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Create items
            foreach ($pricing['items'] as $index => $item) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'resource_type' => $item['resourceType'],
                    'description' => $item['description'],
                    'estimated_hours' => $item['estimatedHours'],
                    'hourly_rate' => $item['hourlyRate'],
                    'internal_hourly_cost' => $item['internalHourlyCost'],
                    'subtotal' => $item['subtotal'],
                    'sort_order' => $index,
                ]);
            }

            return $quotation->load('items', 'lead');
        });
    }

    /**
     * Update quotation
     */
    public function updateQuotation(Quotation $quotation, array $data, User $user): Quotation
    {
        return DB::transaction(function () use ($quotation, $data, $user) {
            // Recalculate if items changed
            if (isset($data['items'])) {
                $pricing = $this->pricingService->calculateFromItems(
                    $data['items'],
                    $data['discountPercentage'] ?? $quotation->discount_percentage,
                    $data['commissionType'] ?? $quotation->commission_type?->value,
                    $data['commissionPercentage'] ?? $quotation->commission_percentage
                );

                // Delete old items and create new ones
                $quotation->items()->delete();

                foreach ($pricing['items'] as $index => $item) {
                    QuotationItem::create([
                        'quotation_id' => $quotation->id,
                        'resource_type' => $item['resourceType'],
                        'description' => $item['description'],
                        'estimated_hours' => $item['estimatedHours'],
                        'hourly_rate' => $item['hourlyRate'],
                        'internal_hourly_cost' => $item['internalHourlyCost'],
                        'subtotal' => $item['subtotal'],
                        'sort_order' => $index,
                    ]);
                }

                $quotation->update([
                    'discount_percentage' => $pricing['discountPercentage'],
                    'final_price' => $data['overridePrice'] ?? $pricing['finalPrice'],
                    'commission_type' => $pricing['commissionType'],
                    'commission_percentage' => $pricing['commissionPercentage'],
                    'commission_amount' => $pricing['commissionAmount'],
                    'internal_cost' => $pricing['internalCost'],
                    'profit_margin' => $pricing['profitMargin'],
                ]);
            }

            // Update other fields
            $quotation->update([
                'template_id' => $data['templateId'] ?? $quotation->template_id,
                'currency' => $data['currency'] ?? $quotation->currency,
                'expiry_date' => $data['expiryDate'] ?? $quotation->expiry_date,
                'commission_user_id' => $data['commissionUserId'] ?? $quotation->commission_user_id,
                'override_price' => $data['overridePrice'] ?? $quotation->override_price,
                'override_reason' => $data['overrideReason'] ?? $quotation->override_reason,
                'notes' => $data['notes'] ?? $quotation->notes,
                'terms_conditions' => $data['termsConditions'] ?? $quotation->terms_conditions,
                'updated_by' => $user->id,
            ]);

            return $quotation->fresh(['items', 'lead']);
        });
    }

    /**
     * Send quotation to client
     */
    public function sendQuotation(Quotation $quotation, User $user): Quotation
    {
        $quotation->update([
            'status' => QuotationStatus::SENT,
            'sent_at' => now(),
            'updated_by' => $user->id,
        ]);

        // TODO: Send email notification to client

        return $quotation->fresh();
    }

    /**
     * Mark quotation as viewed
     */
    public function markAsViewed(Quotation $quotation): Quotation
    {
        if (!$quotation->viewed_at) {
            $quotation->update([
                'viewed_at' => now(),
                'status' => QuotationStatus::VIEWED,
            ]);
        }

        return $quotation->fresh();
    }

    /**
     * Update quotation status
     */
    public function updateStatus(
        Quotation $quotation,
        string $status,
        User $user,
        ?string $rejectionReason = null,
        ?string $rejectionNotes = null
    ): Quotation {
        $updateData = [
            'status' => $status,
            'updated_by' => $user->id,
        ];

        if ($status === QuotationStatus::REJECTED->value) {
            $updateData['rejection_reason'] = $rejectionReason;
            $updateData['rejection_notes'] = $rejectionNotes;
        }

        $quotation->update($updateData);

        return $quotation->fresh();
    }

    /**
     * Get expiring quotations
     */
    public function getExpiringQuotations(int $days = 3): LengthAwarePaginator
    {
        return Quotation::expiringIn($days)
            ->with('lead')
            ->orderBy('expiry_date')
            ->paginate(10);
    }

    /**
     * Calculate pricing preview (without saving)
     */
    public function calculatePricing(array $data): array
    {
        return $this->pricingService->calculateFromItems(
            $data['items'] ?? [],
            $data['discountPercentage'] ?? 0,
            $data['commissionType'] ?? null,
            $data['commissionPercentage'] ?? 0
        );
    }
}
