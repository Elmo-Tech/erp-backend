<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\RecurringTaskConfig;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 *     name="Recurring Tasks",
 *     description="API endpoints for managing recurring task configurations"
 * )
 */
class RecurringTaskController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/recurring-tasks",
     *     summary="Get all recurring task configurations",
     *     tags={"Recurring Tasks"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language preference (en or ar)",
     *
     *         @OA\Schema(type="string", default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[is_active]",
     *         in="query",
     *         required=false,
     *         description="Filter by active status",
     *
     *         @OA\Schema(type="boolean")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[frequency]",
     *         in="query",
     *         required=false,
     *         description="Filter by frequency",
     *
     *         @OA\Schema(type="string", enum={"daily", "weekly", "biweekly", "monthly", "quarterly", "yearly"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Items per page",
     *
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Recurring task configurations retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recurring task configurations retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="configs",
     *                     type="array",
     *
     *                     @OA\Items(
     *
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="frequency", type="string", example="weekly"),
     *                         @OA\Property(property="interval", type="integer", example=1),
     *                         @OA\Property(property="startDate", type="string", format="date", example="2026-01-01"),
     *                         @OA\Property(property="endDate", type="string", format="date", example="2026-12-31"),
     *                         @OA\Property(property="isActive", type="boolean", example=true),
     *                         @OA\Property(property="occurrencesCreated", type="integer", example=10)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=25),
     *                     @OA\Property(property="perPage", type="integer", example=15),
     *                     @OA\Property(property="currentPage", type="integer", example=1),
     *                     @OA\Property(property="totalPages", type="integer", example=2)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $configs = QueryBuilder::for(RecurringTaskConfig::class)
            ->allowedFilters([
                AllowedFilter::exact('is_active'),
                'frequency',
            ])
            ->with('templateTask:id,title,project_id')
            ->withCount('generatedTasks')
            ->latest()
            ->paginate($request->get('per_page', $request->get('perPage', 15)));

        $items = $configs->map(function ($config) {
            $nextOccurrenceDate = $config->getNextOccurrenceDate();

            return [
                'id' => $config->id,
                'templateTaskId' => $config->template_task_id,
                'templateTask' => [
                    'id' => $config->templateTask->id,
                    'title' => $config->templateTask->title,
                    'projectId' => $config->templateTask->project_id,
                ],
                'frequency' => $config->frequency,
                'frequencyLabel' => $config->frequency_label,
                'interval' => $config->interval,
                'daysOfWeek' => $config->days_of_week,
                'dayOfMonth' => $config->day_of_month,
                'startDate' => $config->start_date->toDateString(),
                'endDate' => $config->end_date?->toDateString(),
                'maxOccurrences' => $config->max_occurrences,
                'occurrenceCount' => $config->occurrences_created,
                'occurrencesCreated' => $config->occurrences_created,
                'nextOccurrenceDate' => $nextOccurrenceDate?->toDateString(),
                'isActive' => $config->is_active,
                'generatedTasksCount' => $config->generated_tasks_count,
                'createdAt' => $config->created_at->toDateTimeString(),
                'updatedAt' => $config->updated_at->toDateTimeString(),
            ];
        });

        $data = [
            'items' => $items,
            'configs' => $items,
            'pagination' => [
                'total' => $configs->total(),
                'count' => $configs->count(),
                'perPage' => $configs->perPage(),
                'currentPage' => $configs->currentPage(),
                'totalPages' => $configs->lastPage(),
            ],
        ];

        return ApiResponse::success($data, __('Recurring task configurations retrieved successfully'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/recurring-tasks/{config}",
     *     summary="Get a specific recurring task configuration",
     *     tags={"Recurring Tasks"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="config",
     *         in="path",
     *         required=true,
     *         description="Recurring Task Config ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language preference (en or ar)",
     *
     *         @OA\Schema(type="string", default="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Recurring task configuration retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recurring task configuration retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Configuration not found")
     * )
     */
    public function show(RecurringTaskConfig $config): JsonResponse
    {
        $config->load('templateTask', 'generatedTasks:id,title,due_date,status,recurring_config_id');

        return ApiResponse::success([
            'id' => $config->id,
            'templateTaskId' => $config->template_task_id,
            'templateTask' => [
                'id' => $config->templateTask->id,
                'title' => $config->templateTask->title,
                'projectId' => $config->templateTask->project_id,
            ],
            'frequency' => $config->frequency,
            'frequencyLabel' => $config->frequency_label,
            'interval' => $config->interval,
            'daysOfWeek' => $config->days_of_week,
            'dayOfMonth' => $config->day_of_month,
            'startDate' => $config->start_date->toDateString(),
            'endDate' => $config->end_date?->toDateString(),
            'maxOccurrences' => $config->max_occurrences,
            'occurrencesCreated' => $config->occurrences_created,
            'isActive' => $config->is_active,
            'generatedTasks' => $config->generatedTasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'dueDate' => $task->due_date,
                    'status' => $task->status,
                ];
            }),
            'nextOccurrenceDate' => $config->getNextOccurrenceDate()?->toDateString(),
            'createdAt' => $config->created_at->toDateTimeString(),
            'updatedAt' => $config->updated_at->toDateTimeString(),
        ], __('Recurring task configuration retrieved successfully'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/recurring-tasks",
     *     summary="Create a new recurring task configuration",
     *     tags={"Recurring Tasks"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language preference (en or ar)",
     *
     *         @OA\Schema(type="string", default="en")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"templateTaskId", "frequency", "startDate"},
     *
     *             @OA\Property(property="templateTaskId", type="integer", example=5),
     *             @OA\Property(property="frequency", type="string", enum={"daily", "weekly", "biweekly", "monthly", "quarterly", "yearly"}, example="weekly"),
     *             @OA\Property(property="interval", type="integer", example=1, description="Repeat every X frequency units"),
     *             @OA\Property(property="daysOfWeek", type="array", @OA\Items(type="integer"), example={1, 3, 5}, description="For weekly: 1=Monday, 7=Sunday"),
     *             @OA\Property(property="dayOfMonth", type="integer", example=15, description="For monthly/quarterly/yearly"),
     *             @OA\Property(property="startDate", type="string", format="date", example="2026-01-01"),
     *             @OA\Property(property="endDate", type="string", format="date", example="2026-12-31"),
     *             @OA\Property(property="maxOccurrences", type="integer", example=52, description="Maximum number of tasks to generate"),
     *             @OA\Property(property="isActive", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Recurring task configuration created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recurring task configuration created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'templateTaskId' => ['required', 'integer', 'exists:tasks,id'],
            'frequency' => ['required', 'string', Rule::in([
                RecurringTaskConfig::FREQUENCY_DAILY,
                RecurringTaskConfig::FREQUENCY_WEEKLY,
                RecurringTaskConfig::FREQUENCY_BIWEEKLY,
                RecurringTaskConfig::FREQUENCY_MONTHLY,
                RecurringTaskConfig::FREQUENCY_QUARTERLY,
                RecurringTaskConfig::FREQUENCY_YEARLY,
            ])],
            'interval' => ['nullable', 'integer', 'min:1'],
            'daysOfWeek' => ['nullable', 'array'],
            'daysOfWeek.*' => ['integer', 'min:1', 'max:7'],
            'dayOfMonth' => ['nullable', 'integer', 'min:1', 'max:31'],
            'startDate' => ['required', 'date'],
            'endDate' => ['nullable', 'date', 'after:startDate'],
            'maxOccurrences' => ['nullable', 'integer', 'min:1'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        DB::beginTransaction();
        try {
            $config = RecurringTaskConfig::create([
                'template_task_id' => $validated['templateTaskId'],
                'frequency' => $validated['frequency'],
                'interval' => $validated['interval'] ?? 1,
                'days_of_week' => $validated['daysOfWeek'] ?? null,
                'day_of_month' => $validated['dayOfMonth'] ?? null,
                'start_date' => $validated['startDate'],
                'end_date' => $validated['endDate'] ?? null,
                'max_occurrences' => $validated['maxOccurrences'] ?? null,
                'is_active' => $validated['isActive'] ?? true,
            ]);

            $config->load('templateTask:id,title,project_id');

            DB::commit();

            return ApiResponse::success([
                'id' => $config->id,
                'templateTaskId' => $config->template_task_id,
                'templateTask' => [
                    'id' => $config->templateTask->id,
                    'title' => $config->templateTask->title,
                ],
                'frequency' => $config->frequency,
                'frequencyLabel' => $config->frequency_label,
                'interval' => $config->interval,
                'daysOfWeek' => $config->days_of_week,
                'dayOfMonth' => $config->day_of_month,
                'startDate' => $config->start_date->toDateString(),
                'endDate' => $config->end_date?->toDateString(),
                'maxOccurrences' => $config->max_occurrences,
                'isActive' => $config->is_active,
            ], __('Recurring task configuration created successfully'), 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error(__('Failed to create recurring task configuration'), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/recurring-tasks/{config}",
     *     summary="Update a recurring task configuration",
     *     tags={"Recurring Tasks"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="config",
     *         in="path",
     *         required=true,
     *         description="Recurring Task Config ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language preference (en or ar)",
     *
     *         @OA\Schema(type="string", default="en")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="frequency", type="string", enum={"daily", "weekly", "biweekly", "monthly", "quarterly", "yearly"}),
     *             @OA\Property(property="interval", type="integer", example=1),
     *             @OA\Property(property="daysOfWeek", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="dayOfMonth", type="integer", example=15),
     *             @OA\Property(property="endDate", type="string", format="date"),
     *             @OA\Property(property="maxOccurrences", type="integer", example=52),
     *             @OA\Property(property="isActive", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Recurring task configuration updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recurring task configuration updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Configuration not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, RecurringTaskConfig $config): JsonResponse
    {
        $validated = $request->validate([
            'frequency' => ['sometimes', 'string', Rule::in([
                RecurringTaskConfig::FREQUENCY_DAILY,
                RecurringTaskConfig::FREQUENCY_WEEKLY,
                RecurringTaskConfig::FREQUENCY_BIWEEKLY,
                RecurringTaskConfig::FREQUENCY_MONTHLY,
                RecurringTaskConfig::FREQUENCY_QUARTERLY,
                RecurringTaskConfig::FREQUENCY_YEARLY,
            ])],
            'interval' => ['nullable', 'integer', 'min:1'],
            'daysOfWeek' => ['nullable', 'array'],
            'daysOfWeek.*' => ['integer', 'min:1', 'max:7'],
            'dayOfMonth' => ['nullable', 'integer', 'min:1', 'max:31'],
            'endDate' => ['nullable', 'date', 'after:start_date'],
            'maxOccurrences' => ['nullable', 'integer', 'min:1'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        DB::beginTransaction();
        try {
            $updateData = [];
            if (isset($validated['frequency'])) {
                $updateData['frequency'] = $validated['frequency'];
            }
            if (isset($validated['interval'])) {
                $updateData['interval'] = $validated['interval'];
            }
            if (isset($validated['daysOfWeek'])) {
                $updateData['days_of_week'] = $validated['daysOfWeek'];
            }
            if (isset($validated['dayOfMonth'])) {
                $updateData['day_of_month'] = $validated['dayOfMonth'];
            }
            if (isset($validated['endDate'])) {
                $updateData['end_date'] = $validated['endDate'];
            }
            if (isset($validated['maxOccurrences'])) {
                $updateData['max_occurrences'] = $validated['maxOccurrences'];
            }
            if (isset($validated['isActive'])) {
                $updateData['is_active'] = $validated['isActive'];
            }

            $config->update($updateData);

            DB::commit();

            return ApiResponse::success([
                'id' => $config->id,
                'frequency' => $config->frequency,
                'frequencyLabel' => $config->frequency_label,
                'interval' => $config->interval,
                'daysOfWeek' => $config->days_of_week,
                'dayOfMonth' => $config->day_of_month,
                'endDate' => $config->end_date?->toDateString(),
                'maxOccurrences' => $config->max_occurrences,
                'isActive' => $config->is_active,
            ], __('Recurring task configuration updated successfully'));
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error(__('Failed to update recurring task configuration'), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/recurring-tasks/{config}",
     *     summary="Delete a recurring task configuration",
     *     tags={"Recurring Tasks"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="config",
     *         in="path",
     *         required=true,
     *         description="Recurring Task Config ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language preference (en or ar)",
     *
     *         @OA\Schema(type="string", default="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Recurring task configuration deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recurring task configuration deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Configuration not found")
     * )
     */
    public function destroy(RecurringTaskConfig $config): JsonResponse
    {
        $config->delete();

        return ApiResponse::success([], __('Recurring task configuration deleted successfully'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/recurring-tasks/{config}/generate",
     *     summary="Manually generate the next task instance",
     *     tags={"Recurring Tasks"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="config",
     *         in="path",
     *         required=true,
     *         description="Recurring Task Config ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language preference (en or ar)",
     *
     *         @OA\Schema(type="string", default="en")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Task generated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task generated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Configuration not found"),
     *     @OA\Response(response=422, description="Cannot generate task")
     * )
     */
    public function generate(RecurringTaskConfig $config): JsonResponse
    {
        if (! $config->is_active) {
            return ApiResponse::error(__('Cannot generate task from inactive configuration'), [], 422);
        }

        DB::beginTransaction();
        try {
            $task = $config->generateNextTask();

            if (! $task) {
                return ApiResponse::error(__('No more tasks can be generated from this configuration'), [], 422);
            }

            DB::commit();

            return ApiResponse::success([
                'id' => $task->id,
                'title' => $task->title,
                'dueDate' => $task->due_date,
                'occurrenceNumber' => $task->occurrence_number,
            ], __('Task generated successfully'), 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error(__('Failed to generate task'), ['error' => $e->getMessage()], 500);
        }
    }
}
