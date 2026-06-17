<?php

namespace App\Repositories;

use App\Models\Reward;
use App\Models\RewardClaim;
use App\Models\Spin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EloquentRewardRepository implements RewardRepositoryInterface
{
    public function allActive(): Collection
    {
        return Reward::where('is_active', true)->get();
    }

    public function find(int $id): ?Reward
    {
        return Reward::find($id);
    }

    public function update(int $id, array $data): bool
    {
        $reward = Reward::find($id);
        if ($reward) {
            return $reward->update($data);
        }
        return false;
    }

    public function logSpin(int $submissionId, ?int $rewardId, ?string $ipAddress): Spin
    {
        return Spin::create([
            'submission_id' => $submissionId,
            'reward_id' => $rewardId,
            'ip_address' => $ipAddress,
        ]);
    }

    public function createClaim(int $submissionId, int $rewardId, ?string $claimCode = null): RewardClaim
    {
        return RewardClaim::create([
            'submission_id' => $submissionId,
            'reward_id' => $rewardId,
            'claim_code' => $claimCode ?? $this->generateClaimCode(),
            'status' => 'pending',
        ]);
    }

    public function getClaimsPaginated(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = RewardClaim::with(['submission.state', 'submission.lga', 'reward']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['reward_id'])) {
            $query->where('reward_id', $filters['reward_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('submission', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function getSpinsCountTodayByIp(string $ipAddress): int
    {
        return Spin::where('ip_address', $ipAddress)
            ->whereDate('created_at', Carbon::today())
            ->count();
    }

    public function getRewardWinsTodayCount(int $rewardId): int
    {
        return Spin::where('reward_id', $rewardId)
            ->whereDate('created_at', Carbon::today())
            ->count();
    }

    /**
     * Generate unique voucher/claim code.
     */
    protected function generateClaimCode(): string
    {
        return 'CLAIM-' . strtoupper(bin2hex(random_bytes(4)));
    }
}
