<?php

namespace App\Repositories;

use App\Models\Reward;
use App\Models\RewardClaim;
use App\Models\Spin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface RewardRepositoryInterface
{
    /**
     * Get all active rewards.
     *
     * @return Collection<int, Reward>
     */
    public function allActive(): Collection;

    /**
     * Find a reward by ID.
     */
    public function find(int $id): ?Reward;

    /**
     * Update a reward inventory / settings.
     */
    public function update(int $id, array $data): bool;

    /**
     * Create a spin log for a submission.
     */
    public function logSpin(int $submissionId, ?int $rewardId, ?string $ipAddress): Spin;

    /**
     * Create a reward claim.
     */
    public function createClaim(int $submissionId, int $rewardId, ?string $claimCode = null): RewardClaim;

    /**
     * Get paginated claims.
     *
     * @param array<string, mixed> $filters
     */
    public function getClaimsPaginated(array $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * Check how many spins have occurred from an IP address today.
     */
    public function getSpinsCountTodayByIp(string $ipAddress): int;

    /**
     * Check how many times a reward has been won today.
     */
    public function getRewardWinsTodayCount(int $rewardId): int;
}
