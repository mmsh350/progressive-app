<?php

namespace App\Actions;

use App\Models\Submission;
use App\Repositories\SubmissionRepositoryInterface;
use App\Events\SubmissionApproved;

class ApproveSubmissionAction
{
    public function __construct(
        protected SubmissionRepositoryInterface $submissionRepository
    ) {}

    /**
     * Approve the submission by ID.
     */
    public function execute(int $id): bool
    {
        $success = $this->submissionRepository->updateStatus($id, 'approved');
        
        if ($success) {
            $submission = $this->submissionRepository->find($id);
            if ($submission) {
                event(new SubmissionApproved($submission));
            }
        }

        return $success;
    }
}
