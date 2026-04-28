<?php

namespace App\Modules\Sales\Services;

use App\Enums\CommissionType;
use App\Enums\QuotationStatus;
use App\Modules\Quotation\Models\Quotation;
use App\Modules\Quotation\Models\QuotationItem;
use App\Modules\Quotation\Models\QuotationMilestone;
use App\Modules\User\Models\User;
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
                            ->orWhere('file_number', 'like', "%{$value}%")
                            ->orWhereHas('lead', function ($q) use ($value) {
                                $q->where('name', 'like', "%{$value}%")
                                    ->orWhere('company', 'like', "%{$value}%");
                            });
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'final_price', 'expiry_date', 'sent_at'])
            ->allowedIncludes(['lead', 'items', 'milestones', 'template', 'commissionUser', 'contract', 'task'])
            ->defaultSort('-created_at')
            ->paginate($filters['perPage'] ?? 15);
    }

    /**
     * Create a new quotation with items and milestones
     */
    public function createQuotation(array $data, User $user): Quotation
    {
        return DB::transaction(function () use ($data, $user) {
            // Calculate expiry date from expiryDays
            $expiryDate = isset($data['expiryDays'])
                ? now()->addDays($data['expiryDays'])->toDateString()
                : ($data['expiryDate'] ?? null);

            // Generate quotation number
            $fileNumber = $data['fileNumber'] ?? null;
            $quotationNumber = Quotation::generateQuotationNumber($fileNumber);

            // Calculate pricing from items or basePrice
            $basePrice = $data['basePrice'] ?? 0;

            if (! empty($data['items'])) {
                // Calculate from items
                $pricing = $this->pricingService->calculateFromItems(
                    $data['items'],
                    $data['discountPercentage'] ?? 0,
                    $data['commissionType'] ?? null,
                    $data['commissionPercentage'] ?? 0
                );
                $finalPrice = $data['overridePrice'] ?? $pricing['finalPrice'];
                $discountPercentage = $pricing['discountPercentage'];
                $discountAmount = $pricing['discountAmount'];
            } else {
                // Calculate from basePrice with discount
                $discountType = $data['discountType'] ?? 'percent';
                $discountValue = $data['discountValue'] ?? 0;

                if ($discountType === 'percent') {
                    $discountAmount = $basePrice * ($discountValue / 100);
                } else {
                    $discountAmount = $discountValue;
                }

                $finalPrice = $basePrice - $discountAmount;
                $discountPercentage = $basePrice > 0 ? ($discountAmount / $basePrice) * 100 : 0;
            }

            // Calculate commission
            $commissionType = $data['commissionType'] ?? null;
            $commissionValue = $data['commissionValue'] ?? 0;
            $commissionCalculation = $data['commissionCalculation'] ?? 'add';

            $commissionAmount = 0;
            $commissionPercentage = 0;

            if ($commissionType && $commissionValue > 0) {
                if ($commissionType === CommissionType::MARKUP->value || $commissionType === 'percent') {
                    $commissionPercentage = $commissionValue;
                    $commissionAmount = $finalPrice * ($commissionValue / 100);
                } elseif ($commissionType === CommissionType::MARGIN->value) {
                    $commissionPercentage = $commissionValue;
                    $commissionAmount = $finalPrice - ($finalPrice / (1 + ($commissionValue / 100)));
                } else {
                    $commissionAmount = $commissionValue;
                    $commissionPercentage = $finalPrice > 0 ? ($commissionValue / $finalPrice) * 100 : 0;
                }

                $finalPrice = $finalPrice + $commissionAmount;

                if ($commissionCalculation === 'deduct') {
                    $finalPrice = $finalPrice - (2 * $commissionAmount);
                    $commissionAmount = -$commissionAmount;
                }
            }

            // Internal cost calculation (optional - assume 0 for now)
            $internalCost = $data['internalCost'] ?? 0;
            $profitMargin = $finalPrice > 0 ? (($finalPrice - $internalCost) / $finalPrice) * 100 : 0;

            // Create quotation
            $quotation = Quotation::create([
                'quotation_number' => $quotationNumber,
                'file_number' => $fileNumber,
                'lead_id' => $data['leadId'],
                'proposal_id' => $data['proposalId'] ?? null,
                'template_id' => $data['templateId'] ?? null,
                'task_id' => $data['taskId'] ?? null,
                'scope_description' => $data['title'] ?? null,
                'estimations' => $data['estimations'] ?? null,
                'hourly_rate' => $data['hourlyRate'] ?? 0,
                'discount_percentage' => $discountPercentage,
                'final_price' => $finalPrice,
                'currency' => $data['currency'] ?? 'SAR',
                'status' => $data['status'] ?? QuotationStatus::DRAFT,
                'expiry_date' => $expiryDate,
                'commission_type' => $commissionType,
                'commission_percentage' => $commissionPercentage,
                'commission_amount' => $commissionAmount,
                'commission_user_id' => $data['commissionUserId'] ?? null,
                'internal_cost' => $internalCost,
                'profit_margin' => $profitMargin,
                'override_price' => $data['overridePrice'] ?? null,
                'override_reason' => $data['overrideReason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms_conditions' => $data['termsConditions'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Create items (if provided)
            if (! empty($data['items'])) {
                foreach ($data['items'] as $index => $item) {
                    QuotationItem::create([
                        'quotation_id' => $quotation->id,
                        'resource_type' => $item['resourceType'],
                        'description' => $item['description'],
                        'estimated_hours' => $item['estimatedHours'],
                        'hourly_rate' => $item['hourlyRate'],
                        'internal_hourly_cost' => $item['internalHourlyCost'] ?? 0,
                        'subtotal' => $item['estimatedHours'] * $item['hourlyRate'],
                        'sort_order' => $index,
                    ]);
                }
            }

            // Create milestones (required)
            $milestones = $data['milestones'] ?? [];

            if (! empty($milestones)) {
                foreach ($milestones as $index => $milestone) {
                    // Calculate amount based on final price and percentage
                    $milestoneAmount = $finalPrice * ($milestone['percentage'] / 100);

                    QuotationMilestone::create([
                        'quotation_id' => $quotation->id,
                        'name' => $milestone['name'],
                        'percentage' => $milestone['percentage'],
                        'amount' => round($milestoneAmount, 2),
                        'due_date' => $milestone['dueDate'] ?? null,
                        'description' => $milestone['description'] ?? null,
                        'display_order' => $index,
                    ]);
                }
            }

            return $quotation->load(['items', 'milestones', 'lead']);
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

            // Update milestones if provided
            if (isset($data['milestones'])) {
                $quotation->milestones()->delete();

                $finalPrice = $quotation->fresh()->final_price;

                foreach ($data['milestones'] as $index => $milestone) {
                    $milestoneAmount = $finalPrice * ($milestone['percentage'] / 100);

                    QuotationMilestone::create([
                        'quotation_id' => $quotation->id,
                        'name' => $milestone['name'],
                        'percentage' => $milestone['percentage'],
                        'amount' => round($milestoneAmount, 2),
                        'due_date' => $milestone['dueDate'] ?? null,
                        'description' => $milestone['description'] ?? null,
                        'display_order' => $index,
                    ]);
                }
            }

            // Update other fields
            $quotation->update([
                'file_number' => $data['fileNumber'] ?? $quotation->file_number,
                'template_id' => $data['templateId'] ?? $quotation->template_id,
                'task_id' => $data['taskId'] ?? $quotation->task_id,
                'currency' => $data['currency'] ?? $quotation->currency,
                'expiry_date' => $data['expiryDate'] ?? $quotation->expiry_date,
                'commission_user_id' => $data['commissionUserId'] ?? $quotation->commission_user_id,
                'override_price' => $data['overridePrice'] ?? $quotation->override_price,
                'override_reason' => $data['overrideReason'] ?? $quotation->override_reason,
                'notes' => $data['notes'] ?? $quotation->notes,
                'terms_conditions' => $data['termsConditions'] ?? $quotation->terms_conditions,
                'needs_revision' => $data['needsRevision'] ?? $quotation->needs_revision,
                'revision_notes' => $data['revisionNotes'] ?? $quotation->revision_notes,
                'updated_by' => $user->id,
            ]);

            return $quotation->fresh(['items', 'milestones', 'lead']);
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
        if (! $quotation->viewed_at) {
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
     * Mark quotation as needing revision
     */
    public function markForRevision(Quotation $quotation, string $revisionNotes, User $user): Quotation
    {
        $quotation->update([
            'needs_revision' => true,
            'revision_notes' => $revisionNotes,
            'status' => QuotationStatus::DRAFT,
            'updated_by' => $user->id,
        ]);

        return $quotation->fresh();
    }

    /**
     * Clear revision flag
     */
    public function clearRevision(Quotation $quotation, User $user): Quotation
    {
        $quotation->update([
            'needs_revision' => false,
            'revision_notes' => null,
            'updated_by' => $user->id,
        ]);

        return $quotation->fresh();
    }

    /**
     * Get expiring quotations
     */
    public function getExpiringQuotations(int $days = 3): LengthAwarePaginator
    {
        return Quotation::expiringIn($days)
            ->with('lead', 'milestones')
            ->orderBy('expiry_date')
            ->paginate(10);
    }

    /**
     * Calculate pricing preview (without saving)
     */
    public function calculatePricing(array $data): array
    {
        $basePrice = $data['basePrice'] ?? 0;

        if (! empty($data['items'])) {
            return $this->pricingService->calculateFromItems(
                $data['items'],
                $data['discountPercentage'] ?? 0,
                $data['commissionType'] ?? null,
                $data['commissionPercentage'] ?? 0
            );
        }

        // Calculate from base price
        $discountType = $data['discountType'] ?? 'percent';
        $discountValue = $data['discountValue'] ?? 0;

        if ($discountType === 'percent') {
            $discountAmount = $basePrice * ($discountValue / 100);
        } else {
            $discountAmount = $discountValue;
        }

        $finalPrice = $basePrice - $discountAmount;

        return [
            'basePrice' => $basePrice,
            'discountType' => $discountType,
            'discountValue' => $discountValue,
            'discountAmount' => $discountAmount,
            'discountPercentage' => $basePrice > 0 ? ($discountAmount / $basePrice) * 100 : 0,
            'finalPrice' => $finalPrice,
        ];
    }
}
