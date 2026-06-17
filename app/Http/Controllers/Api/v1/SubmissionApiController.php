<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubmitDeclarationRequest;
use App\Actions\ProcessSubmissionAction;
use App\Services\SubmissionService;
use App\Http\Resources\SubmissionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionApiController extends Controller
{
    public function __construct(
        protected ProcessSubmissionAction $processSubmissionAction,
        protected SubmissionService $submissionService
    ) {}

    /**
     * Submit support declaration and PVC image.
     */
    public function submit(SubmitDeclarationRequest $request): JsonResponse
    {
        $file = $request->file('pvc_selfie');
        
        $submission = $this->processSubmissionAction->execute(
            $request->validated(),
            $file
        );

        return response()->json([
            'message' => 'Support declaration submitted successfully.',
            'reference_number' => $submission->reference_number,
            'submission_id' => $submission->id,
        ], 201);
    }

    /**
     * Track status of support declaration.
     */
    public function status(string $reference): JsonResponse
    {
        $submission = $this->submissionService->getSubmissionStatus($reference);

        if (!$submission) {
            return response()->json([
                'message' => 'Reference number not found.',
            ], 404);
        }

        return response()->json([
            'data' => new SubmissionResource($submission),
        ]);
    }
}
