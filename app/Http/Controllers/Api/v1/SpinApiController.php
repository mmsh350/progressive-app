<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Actions\ProcessSpinAction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class SpinApiController extends Controller
{
    public function __construct(
        protected ProcessSpinAction $processSpinAction
    ) {}

    /**
     * Draw a spin reward for a specific submission.
     */
    public function spin(Request $request): JsonResponse
    {
        $request->validate([
            'submission_id' => 'required|integer|exists:submissions,id',
        ]);

        try {
            $ipAddress = $request->ip() ?? '127.0.0.1';
            
            $reward = $this->processSpinAction->execute(
                (int) $request->submission_id,
                $ipAddress
            );

            return response()->json([
                'success' => true,
                'message' => 'Spin executed successfully.',
                'reward' => [
                    'name' => $reward->name,
                    'type' => $reward->type,
                    'value' => (float) $reward->value,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
