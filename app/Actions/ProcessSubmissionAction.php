<?php

namespace App\Actions;

use App\DTOs\SubmissionDTO;
use App\Models\Submission;
use App\Services\SubmissionService;
use Illuminate\Http\UploadedFile;

class ProcessSubmissionAction
{
    public function __construct(
        protected SubmissionService $submissionService
    ) {}

    /**
     * Execute the support declaration action.
     */
    public function execute(array $data, ?UploadedFile $file = null): Submission
    {
        $dto = SubmissionDTO::fromArray($data);
        return $this->submissionService->createSubmission($dto, $file);
    }
}
