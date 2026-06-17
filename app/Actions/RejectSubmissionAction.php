<?php

namespace App\Actions;

use App\Models\Submission;
use App\Repositories\SubmissionRepositoryInterface;
use App\Events\SubmissionRejected;

class RejectSubmissionAction
{
    public function __construct(
        protected SubmissionRepositoryInterface $submissionRepository
    ) {}

    /**
     * Reject the submission by ID.
     */
    public function execute(int $id): bool
    {
        $success = $this->submissionRepository->updateStatus($id, 'rejected');
        
        if ($success) {
            $submission = $this->submissionRepository->find($id);
            if ($submission) {
                event(new SubmissionRejected($submission));
            }
        }

        return $success;
    }
}
