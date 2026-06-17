<?php

namespace App\Repositories;

use App\Models\Submission;
use App\DTOs\SubmissionDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SubmissionRepositoryInterface
{
    /**
     * Store a new submission from a DTO.
     */
    public function create(SubmissionDTO $dto): Submission;

    /**
     * Find a submission by ID.
     */
    public function find(int $id): ?Submission;

    /**
     * Find a submission by reference number.
     */
    public function findByReference(string $referenceNumber): ?Submission;

    /**
     * Get paginated submissions based on filters.
     *
     * @param array<string, mixed> $filters
     */
    public function getPaginated(array $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * Update the status of a submission.
     */
    public function updateStatus(int $id, string $status): bool;

    /**
     * Perform bulk status updates on multiple submissions.
     *
     * @param array<int> $ids
     */
    public function bulkUpdateStatus(array $ids, string $status): int;

    /**
     * Perform bulk deletion of submissions.
     *
     * @param array<int> $ids
     */
    public function bulkDelete(array $ids): int;

    /**
     * Assign agent to multiple submissions.
     *
     * @param array<int> $ids
     */
    public function bulkAssignAgent(array $ids, int $agentId): int;

    /**
     * Fetch submissions for CSV/Excel export.
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, Submission>
     */
    public function getForExport(array $filters): Collection;
}
