<?php

namespace App\Modules\SOP\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SOP\Actions\CreateDirectiveAction;
use App\Modules\SOP\Actions\GetTodayDirectiveForUserAction;
use App\Modules\SOP\Actions\SubmitDailyCheckinAction;
use App\Modules\SOP\Models\Directive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SOPController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Directive::query()
                ->latest()
                ->paginate(request()->integer('per_page', 15)),
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

    public function update(Request $request, Directive $directive): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phases' => ['sometimes', 'required', 'array'],
            'target_permissions' => ['nullable', 'array'],
            'target_permissions.*' => ['string', 'max:255'],
            'is_recurring' => ['sometimes', 'boolean'],
        ]);

        $directive->update($data);

        return response()->json(['data' => $directive->refresh()]);
    }

    public function destroy(Directive $directive): JsonResponse
    {
        $directive->delete();

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

        $directive = Directive::findOrFail($data['directive_id']);

        foreach ($directive->target_permissions ?? [] as $permission) {
            abort_unless($request->user()->can($permission), 403);
        }

        return response()->json([
            'data' => $action->execute($request->user(), $directive, $data['focus_items']),
        ], 201);
    }
}
