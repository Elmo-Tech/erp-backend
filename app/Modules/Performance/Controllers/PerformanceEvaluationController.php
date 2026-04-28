<?php

namespace App\Modules\Performance\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Performance\Requests\CreateEvaluationPeriodRequest;
use App\Modules\Performance\Requests\UpdateEvaluationRequest;
use App\Modules\Performance\Resources\EvaluationPeriodCollection;
use App\Modules\Performance\Resources\EvaluationPeriodResource;
use App\Modules\Performance\Resources\PerformanceEvaluationCollection;
use App\Modules\Performance\Resources\PerformanceEvaluationResource;
use App\Modules\Performance\Models\EvaluationPeriod;
use App\Modules\Performance\Models\PerformanceEvaluation;
use App\Modules\User\Models\User;
use App\Modules\Performance\Services\BonusCalculationService;
use App\Modules\Performance\Services\PerformanceEvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Performance Evaluation",
 *     description="Performance evaluation and bonus management endpoints"
 * )
 */
class PerformanceEvaluationController extends Controller
{
    public function __construct(
        private PerformanceEvaluationService $evaluationService,
        private BonusCalculationService $bonusService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/performance/periods",
     *     operationId="getEvaluationPeriods",
     *     tags={"Performance Evaluation"},
     *     summary="Get all evaluation periods",
     *     description="Returns paginated list of evaluation periods. Requires view_performance_evaluations permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         required=false,
     *
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="periods", type="array", @OA\Items(
     *                     @OA\Property(property="periodId", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="startDate", type="string", format="date"),
     *                     @OA\Property(property="endDate", type="string", format="date"),
     *                     @OA\Property(property="status", type="string", enum={"draft", "active", "completed"})
     *                 )),
     *                 @OA\Property(property="pagination", type="object")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function periods(Request $request): JsonResponse
    {
        if (! $request->user()->can('view_performance_evaluations')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $periods = EvaluationPeriod::orderByDesc('start_date')
            ->paginate($request->query('perPage', 15));

        return ApiResponse::success(new EvaluationPeriodCollection($periods));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/performance/periods",
     *     operationId="createEvaluationPeriod",
     *     tags={"Performance Evaluation"},
     *     summary="Create a new evaluation period",
     *     description="Creates a new bi-monthly evaluation period. Requires manage_performance_evaluations permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name", "startDate", "endDate"},
     *
     *             @OA\Property(property="name", type="string", example="January-February 2026"),
     *             @OA\Property(property="startDate", type="string", format="date", example="2026-01-01"),
     *             @OA\Property(property="endDate", type="string", format="date", example="2026-02-28")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function createPeriod(CreateEvaluationPeriodRequest $request): JsonResponse
    {
        if (! $request->user()->can('manage_performance_evaluations')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $period = EvaluationPeriod::create([
            'name' => $request->validated('name'),
            'start_date' => $request->validated('startDate'),
            'end_date' => $request->validated('endDate'),
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::success(
            new EvaluationPeriodResource($period),
            __('messages.evaluation_period_created'),
            201
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/performance/periods/{periodId}/activate",
     *     operationId="activateEvaluationPeriod",
     *     tags={"Performance Evaluation"},
     *     summary="Activate an evaluation period",
     *     description="Activates a draft period and creates evaluations for all active employees. Requires manage_performance_evaluations permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="periodId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=400, description="Period already activated"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Period not found")
     * )
     */
    public function activatePeriod(Request $request, int $periodId): JsonResponse
    {
        if (! $request->user()->can('manage_performance_evaluations')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $period = EvaluationPeriod::findOrFail($periodId);

        if ($period->status !== 'draft') {
            return ApiResponse::error(__('messages.period_already_activated'), 400);
        }

        DB::transaction(function () use ($period) {
            $period->update(['status' => 'active']);

            // Create evaluations for all employees
            $employees = User::employees()->where('is_active', true)->get();
            foreach ($employees as $employee) {
                $this->evaluationService->createEvaluation($employee, $period);
            }
        });

        return ApiResponse::success(
            new EvaluationPeriodResource($period->fresh()),
            __('messages.period_activated')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/performance/evaluations",
     *     operationId="getEvaluations",
     *     tags={"Performance Evaluation"},
     *     summary="Get evaluations list",
     *     description="Returns paginated list of performance evaluations with filters. Requires view_performance_evaluations permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[periodId]",
     *         in="query",
     *         required=false,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[status]",
     *         in="query",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"pending", "in_progress", "completed", "approved"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[bonusTier]",
     *         in="query",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"excellent", "good", "average", "poor"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         required=false,
     *
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="evaluations", type="array", @OA\Items(
     *                     @OA\Property(property="evaluationId", type="integer"),
     *                     @OA\Property(property="userId", type="integer"),
     *                     @OA\Property(property="userName", type="string"),
     *                     @OA\Property(property="totalScore", type="number"),
     *                     @OA\Property(property="bonusTier", type="string"),
     *                     @OA\Property(property="bonusAmount", type="number"),
     *                     @OA\Property(property="status", type="string")
     *                 ))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('view_performance_evaluations')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $query = PerformanceEvaluation::with(['user', 'period', 'evaluator']);

        if ($request->has('filter.periodId')) {
            $query->where('evaluation_period_id', $request->query('filter.periodId'));
        }

        if ($request->has('filter.status')) {
            $query->where('status', $request->query('filter.status'));
        }

        if ($request->has('filter.bonusTier')) {
            $query->where('bonus_tier', $request->query('filter.bonusTier'));
        }

        $evaluations = $query->orderByDesc('total_score')
            ->paginate($request->query('perPage', 15));

        return ApiResponse::success(new PerformanceEvaluationCollection($evaluations));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/performance/evaluations/{id}",
     *     operationId="getEvaluation",
     *     tags={"Performance Evaluation"},
     *     summary="Get a specific evaluation with detailed scores",
     *     description="Returns detailed evaluation with all score breakdowns. Users can view their own evaluations.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="evaluation", type="object"),
     *                 @OA\Property(property="summary", type="object",
     *                     @OA\Property(property="scores", type="object",
     *                         @OA\Property(property="technicalQuality", type="object",
     *                             @OA\Property(property="bugSeverity", type="number"),
     *                             @OA\Property(property="codeQuality", type="number"),
     *                             @OA\Property(property="total", type="number"),
     *                             @OA\Property(property="maxScore", type="integer", example=30)
     *                         ),
     *                         @OA\Property(property="innovation", type="object",
     *                             @OA\Property(property="score", type="number"),
     *                             @OA\Property(property="maxScore", type="integer", example=20)
     *                         ),
     *                         @OA\Property(property="productivity", type="object",
     *                             @OA\Property(property="onTimeDelivery", type="number"),
     *                             @OA\Property(property="flexibility", type="number"),
     *                             @OA\Property(property="total", type="number"),
     *                             @OA\Property(property="maxScore", type="integer", example=25)
     *                         ),
     *                         @OA\Property(property="softSkills", type="object",
     *                             @OA\Property(property="communication", type="number"),
     *                             @OA\Property(property="attendance", type="number"),
     *                             @OA\Property(property="initiative", type="number"),
     *                             @OA\Property(property="total", type="number"),
     *                             @OA\Property(property="maxScore", type="integer", example=25)
     *                         )
     *                     ),
     *                     @OA\Property(property="totalScore", type="number"),
     *                     @OA\Property(property="bonusTier", type="string"),
     *                     @OA\Property(property="bonusAmount", type="number")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Evaluation not found")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $evaluation = PerformanceEvaluation::with(['user', 'period', 'evaluator'])->findOrFail($id);

        // Users can view their own evaluations
        if ($evaluation->user_id !== $request->user()->id && ! $request->user()->can('view_performance_evaluations')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $summary = $this->evaluationService->getEvaluationSummary($evaluation);

        return ApiResponse::success([
            'evaluation' => new PerformanceEvaluationResource($evaluation),
            'summary' => $summary,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/performance/evaluations/{id}/calculate",
     *     operationId="calculateEvaluation",
     *     tags={"Performance Evaluation"},
     *     summary="Calculate scores for an evaluation",
     *     description="Automatically calculates all scores based on system data (tasks, attendance, code reviews, innovation logs). Requires manage_performance_evaluations permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Evaluation not found")
     * )
     */
    public function calculate(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->can('manage_performance_evaluations')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $evaluation = PerformanceEvaluation::findOrFail($id);
        $evaluation = $this->evaluationService->calculateScores($evaluation);

        return ApiResponse::success(
            new PerformanceEvaluationResource($evaluation),
            __('messages.evaluation_calculated')
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/performance/evaluations/{id}",
     *     operationId="updateEvaluation",
     *     tags={"Performance Evaluation"},
     *     summary="Manually update evaluation scores",
     *     description="Allows manual adjustment of evaluation scores. Requires manage_performance_evaluations permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="bugSeverityScore", type="number", minimum=0, maximum=15),
     *             @OA\Property(property="codeQualityScore", type="number", minimum=0, maximum=15),
     *             @OA\Property(property="innovationScore", type="number", minimum=0, maximum=20),
     *             @OA\Property(property="onTimeDeliveryScore", type="number", minimum=0, maximum=15),
     *             @OA\Property(property="flexibilityScore", type="number", minimum=0, maximum=10),
     *             @OA\Property(property="communicationScore", type="number", minimum=0, maximum=10),
     *             @OA\Property(property="attendanceScore", type="number", minimum=0, maximum=10),
     *             @OA\Property(property="initiativeScore", type="number", minimum=0, maximum=5),
     *             @OA\Property(property="evaluatorNotes", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Evaluation not found"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function update(UpdateEvaluationRequest $request, int $id): JsonResponse
    {
        if (! $request->user()->can('manage_performance_evaluations')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $evaluation = PerformanceEvaluation::findOrFail($id);
        $data = $request->validated();

        $evaluation->update([
            'bug_severity_score' => $data['bugSeverityScore'] ?? $evaluation->bug_severity_score,
            'code_quality_score' => $data['codeQualityScore'] ?? $evaluation->code_quality_score,
            'innovation_score' => $data['innovationScore'] ?? $evaluation->innovation_score,
            'on_time_delivery_score' => $data['onTimeDeliveryScore'] ?? $evaluation->on_time_delivery_score,
            'flexibility_score' => $data['flexibilityScore'] ?? $evaluation->flexibility_score,
            'communication_score' => $data['communicationScore'] ?? $evaluation->communication_score,
            'attendance_score' => $data['attendanceScore'] ?? $evaluation->attendance_score,
            'initiative_score' => $data['initiativeScore'] ?? $evaluation->initiative_score,
            'evaluator_notes' => $data['evaluatorNotes'] ?? $evaluation->evaluator_notes,
        ]);

        $evaluation->calculateTotals();
        $evaluation->determineBonusTier();
        $evaluation->calculateBonusAmount();
        $evaluation->save();

        return ApiResponse::success(
            new PerformanceEvaluationResource($evaluation),
            __('messages.evaluation_updated')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/performance/evaluations/{id}/approve",
     *     operationId="approveEvaluation",
     *     tags={"Performance Evaluation"},
     *     summary="Approve an evaluation",
     *     description="Approves a completed evaluation. Requires approve_performance_evaluations permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="notes", type="string", description="Optional approval notes")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Evaluation not found")
     * )
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->can('approve_performance_evaluations')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $evaluation = PerformanceEvaluation::findOrFail($id);
        $evaluation = $this->evaluationService->approveEvaluation(
            $evaluation,
            $request->user(),
            $request->input('notes')
        );

        return ApiResponse::success(
            new PerformanceEvaluationResource($evaluation),
            __('messages.evaluation_approved')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/performance/my-evaluations",
     *     operationId="getMyEvaluations",
     *     tags={"Performance Evaluation"},
     *     summary="Get current user's evaluations",
     *     description="Returns paginated list of the authenticated user's performance evaluations.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         required=false,
     *
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function myEvaluations(Request $request): JsonResponse
    {
        $evaluations = PerformanceEvaluation::where('user_id', $request->user()->id)
            ->with('period')
            ->orderByDesc('created_at')
            ->paginate($request->query('perPage', 10));

        return ApiResponse::success(new PerformanceEvaluationCollection($evaluations));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/performance/periods/{periodId}/bonuses",
     *     operationId="getPeriodBonuses",
     *     tags={"Performance Evaluation"},
     *     summary="Get bonus calculations for a period",
     *     description="Returns bonus calculations for all employees in a period. Requires view_bonuses permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="periodId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="periodId", type="integer"),
     *                 @OA\Property(property="periodName", type="string"),
     *                 @OA\Property(property="evaluations", type="array", @OA\Items(
     *                     @OA\Property(property="userId", type="integer"),
     *                     @OA\Property(property="userName", type="string"),
     *                     @OA\Property(property="totalScore", type="number"),
     *                     @OA\Property(property="bonusTier", type="string"),
     *                     @OA\Property(property="bonusTierLabel", type="string"),
     *                     @OA\Property(property="baseAmount", type="number"),
     *                     @OA\Property(property="percentage", type="integer"),
     *                     @OA\Property(property="finalBonusAmount", type="number")
     *                 )),
     *                 @OA\Property(property="summary", type="object",
     *                     @OA\Property(property="totalEmployees", type="integer"),
     *                     @OA\Property(property="totalBonusAmount", type="number"),
     *                     @OA\Property(property="averageScore", type="number")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Period not found")
     * )
     */
    public function periodBonuses(Request $request, int $periodId): JsonResponse
    {
        if (! $request->user()->can('view_bonuses')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $period = EvaluationPeriod::findOrFail($periodId);
        $bonuses = $this->bonusService->calculatePeriodBonuses($period);

        return ApiResponse::success($bonuses);
    }
}
