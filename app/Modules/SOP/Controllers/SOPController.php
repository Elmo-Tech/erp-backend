<?php

namespace App\Modules\SOP\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SOP\Actions\CreateDirectiveAction;
use App\Modules\SOP\Actions\DeleteDirectiveAction;
use App\Modules\SOP\Actions\GetTodayDirectiveForUserAction;
use App\Modules\SOP\Actions\ListDirectivesAction;
use App\Modules\SOP\Actions\SubmitDailyCheckinAction;
use App\Modules\SOP\Actions\UpdateDirectiveAction;
use App\Modules\SOP\Models\Directive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SOPController extends Controller
{
    public function index(Request $request, ListDirectivesAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->execute($request->integer('per_page', 15)),
        ]);
    }

    public function store(Request $request, CreateDirectiveAction $action): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phases' => ['required', 'array'],
            'target_permissions' => ['nullable', 'array'],
            'target_permissions.*' => ['string', 'max:255'],
            'is_recurring' => ['sometimes', 'boolean'],
        ]);

        $directive = $action->execute($data, $request->user());

        return response()->json(['data' => $directive], 201);
    }

    public function show(Directive $directive): JsonResponse
    {
        return response()->json(['data' => $directive]);
    }

    public function update(Request $request, Directive $directive, UpdateDirectiveAction $action): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phases' => ['sometimes', 'required', 'array'],
            'target_permissions' => ['nullable', 'array'],
            'target_permissions.*' => ['string', 'max:255'],
            'is_recurring' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => $action->execute($directive, $data)]);
    }

    public function destroy(Directive $directive, DeleteDirectiveAction $action): JsonResponse
    {
        $action->execute($directive);

        return response()->json(status: 204);
    }

    public function today(Request $request, GetTodayDirectiveForUserAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->execute($request->user()),
        ]);
    }

    public function submit(Request $request, SubmitDailyCheckinAction $action): JsonResponse
    {
        $data = $request->validate([
            'directive_id' => ['required', 'integer', 'exists:directives,id'],
            'focus_items' => ['required', 'array', 'size:3'],
            'focus_items.*' => ['required', 'string', 'max:500'],
        ]);

        return response()->json([
            'data' => $action->executeForDirectiveId($request->user(), $data['directive_id'], $data['focus_items']),
        ], 201);
    }
}
