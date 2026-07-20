<?php

namespace App\Modules\Client\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientActivity;
use App\Modules\Client\Resources\ClientActivityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientActivityController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function index(Request $request, Client $client): JsonResponse
    {
        $this->authorize('viewActivity', $client);

        $activities = ClientActivity::query()
            ->where('client_id', $client->id)
            ->with('user:id,name,email')
            ->latest()
            ->limit((int) $request->integer('limit', 50))
            ->get();

        return ApiResponse::success([
            'activities' => ClientActivityResource::collection($activities),
        ], 'Client activities retrieved successfully');
    }
}
