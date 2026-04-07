<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Performance\CreateCodeReviewRequest;
use App\Http\Requests\V1\Performance\UpdateCodeReviewRequest;
use App\Http\Resources\V1\Performance\CodeReviewCollection;
use App\Http\Resources\V1\Performance\CodeReviewResource;
use App\Models\CodeReview;
use App\Models\Task;
use App\Models\User;
use App\Services\Performance\CodeReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Code Reviews",
 *     description="Code review management for performance evaluation"
 * )
 */
class CodeReviewController extends Controller
{
    public function __construct(
        private CodeReviewService $codeReviewService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/code-reviews",
     *     operationId="getCodeReviews",
     *     tags={"Code Reviews"},
     *     summary="Get code reviews list",
     *     description="Returns paginated list of code reviews. Users see their own reviews unless they have view_all_code_reviews permission.",
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
     *         name="filter[asReviewer]",
     *         in="query",
     *         required=false,
     *         description="Filter reviews where current user is the reviewer",
     *
     *         @OA\Schema(type="string", enum={"true", "false"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[asDeveloper]",
     *         in="query",
     *         required=false,
     *         description="Filter reviews where current user is the developer",
     *
     *         @OA\Schema(type="string", enum={"true", "false"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[status]",
     *         in="query",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"pending", "approved", "needs_revision", "rejected"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[taskId]",
     *         in="query",
     *         required=false,
     *
     *         @OA\Schema(type="integer")
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
     *                 @OA\Property(property="codeReviews", type="array", @OA\Items(
     *                     @OA\Property(property="reviewId", type="integer"),
     *                     @OA\Property(property="taskId", type="integer"),
     *                     @OA\Property(property="taskTitle", type="string"),
     *                     @OA\Property(property="reviewerName", type="string"),
     *                     @OA\Property(property="developerName", type="string"),
     *                     @OA\Property(property="scores", type="object"),
     *                     @OA\Property(property="status", type="string")
     *                 ))
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = CodeReview::with(['task', 'reviewer', 'developer']);

        // Filter by role
        if ($request->has('filter.asReviewer') && $request->query('filter.asReviewer') === 'true') {
            $query->where('reviewer_id', $request->user()->id);
        } elseif ($request->has('filter.asDeveloper') && $request->query('filter.asDeveloper') === 'true') {
            $query->where('developer_id', $request->user()->id);
        } elseif (! $request->user()->can('view_all_code_reviews')) {
            $query->where(function ($q) use ($request) {
                $q->where('reviewer_id', $request->user()->id)
                    ->orWhere('developer_id', $request->user()->id);
            });
        }

        if ($request->has('filter.status')) {
            $query->where('status', $request->query('filter.status'));
        }

        if ($request->has('filter.taskId')) {
            $query->where('task_id', $request->query('filter.taskId'));
        }

        $reviews = $query->orderByDesc('created_at')
            ->paginate($request->query('perPage', 15));

        return ApiResponse::success(new CodeReviewCollection($reviews));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/code-reviews",
     *     operationId="createCodeReview",
     *     tags={"Code Reviews"},
     *     summary="Create a new code review",
     *     description="Creates a new code review for a task. The authenticated user becomes the reviewer.",
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
     *             required={"taskId", "developerId"},
     *
     *             @OA\Property(property="taskId", type="integer", description="Task being reviewed"),
     *             @OA\Property(property="developerId", type="integer", description="Developer who wrote the code"),
     *             @OA\Property(property="codeQualityScore", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="readabilityScore", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="scalabilityScore", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="testingScore", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="documentationScore", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="reviewNotes", type="string"),
     *             @OA\Property(property="improvementSuggestions", type="string")
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
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function store(CreateCodeReviewRequest $request): JsonResponse
    {
        $data = $request->validated();
        $task = Task::findOrFail($data['taskId']);
        $developer = User::findOrFail($data['developerId']);

        $review = $this->codeReviewService->createReview(
            $task,
            $request->user(),
            $developer,
            $data
        );

        return ApiResponse::success(
            new CodeReviewResource($review),
            __('messages.code_review_created'),
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/code-reviews/{id}",
     *     operationId="getCodeReview",
     *     tags={"Code Reviews"},
     *     summary="Get a specific code review",
     *     description="Returns detailed code review. Only reviewer, developer, or users with view_all_code_reviews can access.",
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
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $review = CodeReview::with(['task', 'reviewer', 'developer'])->findOrFail($id);

        // Check access
        $canView = $review->reviewer_id === $request->user()->id
            || $review->developer_id === $request->user()->id
            || $request->user()->can('view_all_code_reviews');

        if (! $canView) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        return ApiResponse::success(new CodeReviewResource($review));
    }

    /**
     * @OA\Put(
     *     path="/api/v1/code-reviews/{id}",
     *     operationId="updateCodeReview",
     *     tags={"Code Reviews"},
     *     summary="Update a code review",
     *     description="Updates code review scores. Only the reviewer or users with manage_code_reviews can update.",
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
     *             @OA\Property(property="codeQualityScore", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="readabilityScore", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="scalabilityScore", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="testingScore", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="documentationScore", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="reviewNotes", type="string"),
     *             @OA\Property(property="improvementSuggestions", type="string")
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
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(UpdateCodeReviewRequest $request, int $id): JsonResponse
    {
        $review = CodeReview::findOrFail($id);

        // Only reviewer can update
        if ($review->reviewer_id !== $request->user()->id && ! $request->user()->can('manage_code_reviews')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $review = $this->codeReviewService->updateReview($review, $request->validated());

        return ApiResponse::success(
            new CodeReviewResource($review),
            __('messages.code_review_updated')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/code-reviews/{id}/approve",
     *     operationId="approveCodeReview",
     *     tags={"Code Reviews"},
     *     summary="Approve a code review",
     *     description="Marks the code review as approved. Only the reviewer or users with manage_code_reviews can approve.",
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
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $review = CodeReview::findOrFail($id);

        if ($review->reviewer_id !== $request->user()->id && ! $request->user()->can('manage_code_reviews')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $review = $this->codeReviewService->approveReview($review);

        return ApiResponse::success(
            new CodeReviewResource($review),
            __('messages.code_review_approved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/code-reviews/{id}/request-revision",
     *     operationId="requestRevisionCodeReview",
     *     tags={"Code Reviews"},
     *     summary="Request revision for a code review",
     *     description="Marks the code review as needing revision with improvement suggestions.",
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
     *             required={"suggestions"},
     *
     *             @OA\Property(property="suggestions", type="string", description="Improvement suggestions for the developer")
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
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function requestRevision(Request $request, int $id): JsonResponse
    {
        $review = CodeReview::findOrFail($id);

        if ($review->reviewer_id !== $request->user()->id && ! $request->user()->can('manage_code_reviews')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $request->validate([
            'suggestions' => 'required|string|max:2000',
        ]);

        $review = $this->codeReviewService->requestRevision($review, $request->input('suggestions'));

        return ApiResponse::success(
            new CodeReviewResource($review),
            __('messages.revision_requested')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/code-reviews/{id}/reject",
     *     operationId="rejectCodeReview",
     *     tags={"Code Reviews"},
     *     summary="Reject a code review",
     *     description="Marks the code review as rejected with a reason.",
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
     *             required={"reason"},
     *
     *             @OA\Property(property="reason", type="string", description="Reason for rejecting the code review")
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
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $review = CodeReview::findOrFail($id);

        if ($review->reviewer_id !== $request->user()->id && ! $request->user()->can('manage_code_reviews')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $review = $this->codeReviewService->rejectReview($review, $request->input('reason'));

        return ApiResponse::success(
            new CodeReviewResource($review),
            __('messages.code_review_rejected')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/code-reviews/my-pending",
     *     operationId="getMyPendingReviews",
     *     tags={"Code Reviews"},
     *     summary="Get pending reviews for current user as reviewer",
     *     description="Returns list of code reviews pending the current user's review.",
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
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function myPending(Request $request): JsonResponse
    {
        $reviews = $this->codeReviewService->getPendingReviewsForReviewer($request->user());

        return ApiResponse::success(CodeReviewResource::collection($reviews));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/code-reviews/my-stats",
     *     operationId="getMyCodeReviewStats",
     *     tags={"Code Reviews"},
     *     summary="Get code review statistics for current user",
     *     description="Returns statistics about the current user's code reviews as a developer.",
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
     *         name="startDate",
     *         in="query",
     *         required=false,
     *
     *         @OA\Schema(type="string", format="date")
     *     ),
     *
     *     @OA\Parameter(
     *         name="endDate",
     *         in="query",
     *         required=false,
     *
     *         @OA\Schema(type="string", format="date")
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
     *                 @OA\Property(property="totalReviews", type="integer"),
     *                 @OA\Property(property="approved", type="integer"),
     *                 @OA\Property(property="needsRevision", type="integer"),
     *                 @OA\Property(property="rejected", type="integer"),
     *                 @OA\Property(property="averageScores", type="object",
     *                     @OA\Property(property="codeQuality", type="number"),
     *                     @OA\Property(property="readability", type="number"),
     *                     @OA\Property(property="scalability", type="number"),
     *                     @OA\Property(property="testing", type="number"),
     *                     @OA\Property(property="documentation", type="number"),
     *                     @OA\Property(property="overall", type="number")
     *                 ),
     *                 @OA\Property(property="approvalRate", type="number")
     *             )
     *         )
     *     )
     * )
     */
    public function myStats(Request $request): JsonResponse
    {
        $startDate = $request->query('startDate')
            ? \Carbon\Carbon::parse($request->query('startDate'))
            : now()->startOfMonth();
        $endDate = $request->query('endDate')
            ? \Carbon\Carbon::parse($request->query('endDate'))
            : now()->endOfMonth();

        $stats = $this->codeReviewService->getDeveloperStats(
            $request->user(),
            $startDate,
            $endDate
        );

        return ApiResponse::success($stats);
    }
}
