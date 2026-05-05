<?php

namespace App\Modules\Settings\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\BaseDictionaryModel;
use App\Modules\Settings\Resources\DictionaryResource;
use App\Modules\Settings\Services\DictionaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BaseDictionaryController extends Controller
{
    public function __construct(
        protected DictionaryService $dictionaryService
    ) {}

    public function index(string $entity): JsonResponse
    {
        $model = $this->resolveModel($entity);

        if ($model === null) {
            return ApiResponse::notFound('Dictionary entity not found');
        }

        $items = $model->newQuery()
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            DictionaryResource::collection($items),
            'Dictionary records retrieved successfully'
        );
    }

    public function store(Request $request, string $entity): JsonResponse
    {
        $model = $this->resolveModel($entity);

        if ($model === null) {
            return ApiResponse::notFound('Dictionary entity not found');
        }

        $payload = $this->validatedPayload($request, $model);
        $item = $model->newQuery()->create($payload);

        return ApiResponse::success(
            new DictionaryResource($item),
            'Dictionary record created successfully',
            201
        );
    }

    public function update(Request $request, string $entity, int|string $id): JsonResponse
    {
        $model = $this->resolveModel($entity);

        if ($model === null) {
            return ApiResponse::notFound('Dictionary entity not found');
        }

        $item = $model->newQuery()->find($id);

        if ($item === null) {
            return ApiResponse::notFound('Dictionary record not found');
        }

        $payload = $this->validatedPayload($request, $model, $item->getKey());
        $item->update($payload);

        return ApiResponse::success(
            new DictionaryResource($item->refresh()),
            'Dictionary record updated successfully'
        );
    }

    public function destroy(string $entity, int|string $id): JsonResponse
    {
        abort_if(! request()->user()?->can('delete_settings'), 403, 'Unauthorized');

        $model = $this->resolveModel($entity);

        if ($model === null) {
            return ApiResponse::notFound('Dictionary entity not found');
        }

        $item = $model->newQuery()->find($id);

        if ($item === null) {
            return ApiResponse::notFound('Dictionary record not found');
        }

        $item->delete();

        return ApiResponse::success(null, 'Dictionary record deleted successfully');
    }

    protected function resolveModel(string $entity): ?BaseDictionaryModel
    {
        return $this->dictionaryService->modelFor($entity);
    }

    /**
     * @return array{name: string, slug: string, is_active: bool}
     */
    protected function validatedPayload(
        Request $request,
        BaseDictionaryModel $model,
        int|string|null $ignoreId = null
    ): array {
        $payload = [
            'name' => $request->input('name'),
            'slug' => $request->input('slug'),
            'is_active' => $request->input(
                'is_active',
                $request->input('isActive', true)
            ),
        ];

        if (filled($payload['name']) && blank($payload['slug'])) {
            $payload['slug'] = Str::slug((string) $payload['name']);
        }

        return Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique($model->getTable(), 'slug')->ignore($ignoreId),
            ],
            'is_active' => ['boolean'],
        ])->validate();
    }
}
