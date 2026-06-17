<?php

namespace App\Repositories;

use App\Models\Submission;
use App\Models\Wish;
use App\Models\SubmissionImage;
use App\DTOs\SubmissionDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EloquentSubmissionRepository implements SubmissionRepositoryInterface
{
    public function create(SubmissionDTO $dto): Submission
    {
        return DB::transaction(function () use ($dto) {
            $submission = Submission::create($dto->toArray());

            if ($dto->wish) {
                $submission->wish()->create([
                    'wish_category_id' => $dto->wish->wishCategoryId,
                    'title' => $dto->wish->title,
                    'description' => $dto->wish->description,
                ]);
            }

            if ($dto->imagePath) {
                $submission->image()->create([
                    'image_path' => $dto->imagePath,
                    'watermark_applied' => false,
                ]);
            }

            return $submission;
        });
    }

    public function find(int $id): ?Submission
    {
        return Submission::with(['state', 'lga', 'ward', 'occupation', 'wish', 'image', 'spin', 'rewardClaim'])->find($id);
    }

    public function findByReference(string $referenceNumber): ?Submission
    {
        return Submission::with(['state', 'lga', 'ward', 'occupation', 'wish', 'image', 'spin', 'rewardClaim'])
            ->where('reference_number', $referenceNumber)
            ->first();
    }

    public function getPaginated(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Submission::with(['state', 'lga', 'occupation']);

        $this->applyFilters($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $submission = Submission::find($id);
        if ($submission) {
            $submission->status = $status;
            return $submission->save();
        }
        return false;
    }

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->updateStatus($id, $status)) {
                $count++;
            }
        }
        return $count;
    }

    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $submissions = Submission::whereIn('id', $ids)->get();
            $count = 0;
            foreach ($submissions as $sub) {
                $sub->delete();
                $count++;
            }
            return $count;
        });
    }

    public function bulkAssignAgent(array $ids, int $agentId): int
    {
        return Submission::whereIn('id', $ids)->update(['agent_id' => $agentId]);
    }

    public function getForExport(array $filters): Collection
    {
        $query = Submission::with(['state', 'lga', 'ward', 'occupation', 'wish.category']);

        $this->applyFilters($query, $filters);

        return $query->latest()->get();
    }

    /**
     * Apply filter conditions to the query.
     */
    protected function applyFilters($query, array $filters): void
    {
        if (!empty($filters['search'])) {
            // Check if Meilisearch/Scout is configured, but fallback to DB query if driver is database or local
            if (config('scout.driver') === 'database') {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('phone_number', 'like', "%{$search}%")
                      ->orWhere('reference_number', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            } else {
                // If Scout has Meilisearch/Algolia driver, let's query via Scout ID mapping.
                // For safety in local fallback, we can keep the simple DB search or resolve via scout:
                $ids = Submission::search($filters['search'])->keys();
                $query->whereIn('id', $ids);
            }
        }

        if (!empty($filters['state_id'])) {
            $query->where('state_id', $filters['state_id']);
        }

        if (!empty($filters['lga_id'])) {
            $query->where('lga_id', $filters['lga_id']);
        }

        if (!empty($filters['occupation_id'])) {
            $query->where('occupation_id', $filters['occupation_id']);
        }

        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (!empty($filters['age_group'])) {
            $query->where('age_group', $filters['age_group']);
        }

        if (isset($filters['voted_2023'])) {
            $query->where('voted_2023', filter_var($filters['voted_2023'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['vote_2027'])) {
            $query->where('vote_2027', filter_var($filters['vote_2027'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['agent_id'])) {
            $query->where('agent_id', $filters['agent_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }
}
