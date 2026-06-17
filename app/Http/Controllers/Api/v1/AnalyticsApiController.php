<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalyticsApiController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Get aggregate platform KPIs for dashboards.
     */
    public function stats(Request $request): JsonResponse
    {
        $stateId = $request->has('state_id') ? (int) $request->state_id : null;
        
        $stats = $this->analyticsService->getDashboardStats($stateId);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
