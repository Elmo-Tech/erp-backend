<?php

namespace App\Http\Resources\V1\Contract;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ContractResource",
 *     type="object",
 *     @OA\Property(property="contractId", type="integer", example=1),
 *     @OA\Property(property="contractNumber", type="string", example="CT-2026-0001"),
 *     @OA\Property(property="quotationId", type="integer", example=1),
 *     @OA\Property(property="projectId", type="integer", nullable=true, example=1),
 *     @OA\Property(property="templateId", type="integer", nullable=true, example=1),
 *     @OA\Property(property="title", type="string", example="Service Agreement - John Doe"),
 *     @OA\Property(property="status", type="string", example="draft"),
 *     @OA\Property(property="content", type="string", example="<html>Contract content...</html>"),
 *     @OA\Property(property="totalAmount", type="number", format="float", example=5000.00),
 *     @OA\Property(property="currency", type="string", example="USD"),
 *     @OA\Property(property="paymentTerms", type="string", example="Payment due within 30 days"),
 *     @OA\Property(property="startDate", type="string", format="date", example="2026-02-01"),
 *     @OA\Property(property="endDate", type="string", nullable=true, format="date", example="2026-12-31"),
 *     @OA\Property(property="signedAt", type="string", nullable=true, format="date-time", example="2026-02-01T10:30:00Z"),
 *     @OA\Property(property="signedByClient", type="string", nullable=true, example="John Doe"),
 *     @OA\Property(property="createdAt", type="string", format="date-time", example="2026-02-01T10:00:00Z"),
 *     @OA\Property(property="updatedAt", type="string", format="date-time", example="2026-02-01T10:30:00Z"),
 *     @OA\Property(
 *         property="partyOne",
 *         type="object",
 *         @OA\Property(property="company", type="string", example="Your Company"),
 *         @OA\Property(property="representative", type="string", example="Company Representative"),
 *         @OA\Property(property="title", type="string", example="CEO"),
 *         @OA\Property(property="address", type="string", example="Company Address")
 *     ),
 *     @OA\Property(
 *         property="partyTwo",
 *         type="object",
 *         @OA\Property(property="name", type="string", example="John Doe"),
 *         @OA\Property(property="email", type="string", example="john@example.com"),
 *         @OA\Property(property="company", type="string", example="Acme Corp"),
 *         @OA\Property(property="phone", type="string", example="+1234567890")
 *     ),
 *     @OA\Property(
 *         property="quotation",
 *         type="object",
 *         @OA\Property(property="quotationId", type="integer", example=1),
 *         @OA\Property(property="finalPrice", type="number", format="float", example=5000.00),
 *         @OA\Property(property="currency", type="string", example="USD"),
 *         @OA\Property(property="status", type="string", example="approved"),
 *         @OA\Property(
 *             property="lead",
 *             type="object",
 *             @OA\Property(property="leadId", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="John Doe"),
 *             @OA\Property(property="email", type="string", example="john@example.com"),
 *             @OA\Property(property="company", type="string", example="Acme Corp")
 *         )
 *     ),
 *     @OA\Property(
 *         property="project",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="projectId", type="integer", example=1),
 *         @OA\Property(property="title", type="string", example="Project for Acme Corp"),
 *         @OA\Property(property="status", type="string", example="in_progress")
 *     )
 * )
 */
class ContractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'contractId' => $this->id,
            'contractNumber' => $this->contract_number ?? "",
            'quotationId' => $this->quotation_id,
            'projectId' => $this->project_id,
            'templateId' => $this->template_id,
            'title' => $this->title ?? "",
            'status' => $this->status->value,
            'content' => $this->content ?? "",
            'totalAmount' => (float) $this->total_amount,
            'currency' => $this->currency ?? "USD",
            'paymentTerms' => $this->payment_terms ?? "",
            'startDate' => $this->start_date?->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'signedAt' => $this->signed_at?->toISOString(),
            'signedByClient' => $this->signed_by_client ?? "",
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
            
            // Party information
            'partyOne' => $this->party_one ?? [],
            'partyTwo' => $this->party_two ?? [],
            
            // Relationships
            'quotation' => $this->whenLoaded('quotation', function () {
                return [
                    'quotationId' => $this->quotation->id,
                    'finalPrice' => (float) $this->quotation->final_price,
                    'currency' => $this->quotation->currency,
                    'status' => $this->quotation->status->value,
                    'lead' => $this->whenLoaded('quotation.lead', function () {
                        return [
                            'leadId' => $this->quotation->lead->id,
                            'name' => $this->quotation->lead->name ?? "",
                            'email' => $this->quotation->lead->email ?? "",
                            'company' => $this->quotation->lead->company ?? "",
                        ];
                    }),
                ];
            }),
            
            'project' => $this->whenLoaded('project', function () {
                return $this->project ? [
                    'projectId' => $this->project->id,
                    'title' => $this->project->title ?? "",
                    'status' => $this->project->status->value,
                ] : null;
            }),
        ];
    }
}