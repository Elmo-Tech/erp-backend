<?php

namespace App\Modules\Notes\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Client\Models\Client;
use App\Modules\Lead\Models\Lead;
use App\Modules\Notes\Models\Note;
use App\Modules\Notes\Resources\NoteResource;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    private const MODEL_TYPE_MAP = [
        'client' => Client::class,
        'project' => Project::class,
        'lead' => Lead::class,
        Client::class => Client::class,
        Project::class => Project::class,
        Lead::class => Lead::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request, [
            'modelType' => ['required', 'string', Rule::in(array_keys(self::MODEL_TYPE_MAP))],
            'modelId' => ['required', 'integer', 'min:1'],
        ]);

        $modelType = self::MODEL_TYPE_MAP[$data['modelType']];
        $subject = $this->resolveSubject($modelType, (int) $data['modelId']);
        $this->authorizeSubject($request, $subject);

        $notes = Note::query()
            ->where('model_type', $modelType)
            ->where('model_id', (int) $data['modelId'])
            ->with('author:id,name,email')
            ->orderByDesc('noted_at')
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success([
            'notes' => NoteResource::collection($notes),
        ], 'Notes retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request, [
            'modelType' => ['required', 'string', Rule::in(array_keys(self::MODEL_TYPE_MAP))],
            'modelId' => ['required', 'integer', 'min:1'],
            'body' => ['required', 'string', 'max:10000'],
            'notedAt' => ['nullable', 'date'],
        ]);

        $modelType = self::MODEL_TYPE_MAP[$data['modelType']];
        $subject = $this->resolveSubject($modelType, (int) $data['modelId']);
        $this->authorizeSubject($request, $subject);

        $note = Note::create([
            'model_type' => $modelType,
            'model_id' => $subject->getKey(),
            'body' => $data['body'],
            'noted_at' => isset($data['notedAt']) ? Carbon::parse($data['notedAt']) : now(),
            'created_by' => $request->user()?->id,
        ]);

        return ApiResponse::success(
            new NoteResource($note->load('author:id,name,email')),
            'Note created successfully',
            201
        );
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, array $rules): array
    {
        $payload = [
            'modelType' => $request->input('modelType', $request->input('model_type')),
            'modelId' => $request->input('modelId', $request->input('model_id')),
            'body' => $request->input('body'),
            'notedAt' => $request->input('notedAt', $request->input('noted_at')),
        ];

        $validator = Validator::make($payload, $rules, [
            'modelType.required' => 'Note subject is required.',
            'modelType.in' => 'Note subject is invalid.',
            'modelId.required' => 'Note subject is required.',
            'body.required' => 'Note body is required.',
        ]);

        if ($validator->fails()) {
            abort(response()->json([
                'success' => false,
                'message' => __('validation.failed'),
                'errors' => $validator->errors()->toArray(),
            ], 422));
        }

        return $validator->validated();
    }

    /**
     * @param  class-string<Model>  $modelType
     */
    private function resolveSubject(string $modelType, int $modelId): Model
    {
        return $modelType::query()->findOrFail($modelId);
    }

    private function authorizeSubject(Request $request, Model $subject): void
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $allowed = match (true) {
            $subject instanceof Client, $subject instanceof Project => Gate::forUser($user)->allows('view', $subject),
            $subject instanceof Lead => $user->can('view_leads') || $user->can('manage_leads'),
            default => false,
        };

        abort_unless($allowed, 403, 'Unauthorized note subject');
    }
}
