<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\Reward;
use App\Models\Spin;
use App\Models\RewardClaim;
use App\Models\SystemSetting;
use App\Repositories\RewardRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Exception;

class RewardEngineService
{
    public function __construct(
        protected RewardRepositoryInterface $rewardRepository
    ) {}

    /**
     * Citizen draws a spin and gets a reward.
     */
    public function spin(int $submissionId, string $ipAddress): Reward
    {
        return DB::transaction(function () use ($submissionId, $ipAddress) {
            // 1. Validate submission exists and has not spun yet
            $submission = Submission::with(['spin'])->find($submissionId);
            if (!$submission) {
                throw new Exception("Submission record not found.");
            }

            if ($submission->spin) {
                throw new Exception("This support declaration has already utilized its spin.");
            }

            // 2. Check IP abuse settings
            $spinEnabledSetting = SystemSetting::where('key', 'spin_enabled')->first();
            $spinEnabled = $spinEnabledSetting ? filter_var($spinEnabledSetting->value, FILTER_VALIDATE_BOOLEAN) : true;
            if (!$spinEnabled) {
                throw new Exception("Spin rewards are currently disabled.");
            }

            $ipLimitSetting = SystemSetting::where('key', 'max_daily_spins_per_ip')->first();
            $maxDailySpins = $ipLimitSetting ? (int) $ipLimitSetting->value : 3;

            $ipSpinsCount = $this->rewardRepository->getSpinsCountTodayByIp($ipAddress);
            if ($ipSpinsCount >= $maxDailySpins) {
                throw new Exception("Daily IP address spin limit exceeded. Please try again tomorrow.");
            }

            // 3. Select a reward using probability engine
            $rewards = $this->rewardRepository->allActive();
            $selectedReward = $this->drawReward($rewards);

            // 4. Validate limits for selected reward
            $finalReward = $selectedReward;
            if ($selectedReward->type !== 'none') {
                $isLimitOk = $this->checkRewardLimits($selectedReward);
                if (!$isLimitOk) {
                    // Fallback to "No Reward"
                    $finalReward = Reward::where('type', 'none')->first() 
                        ?? Reward::create([
                            'name' => 'No Reward',
                            'type' => 'none',
                            'probability' => 100.00,
                            'is_active' => true
                        ]);
                }
            }

            // 5. Log spin and create claim if a real reward was won
            $this->rewardRepository->logSpin($submissionId, $finalReward->id, $ipAddress);

            if ($finalReward->type !== 'none') {
                $claim = $this->rewardRepository->createClaim($submissionId, $finalReward->id);
                // Dispatch notification for reward claim
                event(new \App\Events\RewardClaimed($claim));
            }

            return $finalReward;
        });
    }

    /**
     * Choose a reward based on probability weightings.
     */
    protected function drawReward($rewards): Reward
    {
        if ($rewards->isEmpty()) {
            return Reward::firstOrCreate([
                'name' => 'No Reward',
                'type' => 'none',
                'probability' => 100.00,
                'is_active' => true
            ]);
        }

        // Sum of all probabilities
        $totalWeight = 0.00;
        foreach ($rewards as $reward) {
            $totalWeight += (float) $reward->probability;
        }

        // Random roll between 0 and total probability weight
        $roll = mt_rand(0, 10000) / 100.00; // supports decimals, e.g. 0.00 to 100.00
        $roll = min($roll, $totalWeight); // cap at total weight if different from 100

        $currentSum = 0.00;
        foreach ($rewards as $reward) {
            $currentSum += (float) $reward->probability;
            if ($roll <= $currentSum) {
                return $reward;
            }
        }

        return $rewards->last();
    }

    /**
     * Check if a reward has daily limits or inventory capacity left.
     */
    protected function checkRewardLimits(Reward $reward): bool
    {
        // 1. Check inventory limit (total lifetime claims allowed)
        if ($reward->inventory_limit > 0) {
            $totalClaims = RewardClaim::where('reward_id', $reward->id)->count();
            if ($totalClaims >= $reward->inventory_limit) {
                return false;
            }
        }

        // 2. Check daily limits
        if ($reward->daily_limit > 0) {
            $winsToday = $this->rewardRepository->getRewardWinsTodayCount($reward->id);
            if ($winsToday >= $reward->daily_limit) {
                return false;
            }
        }

        return true;
    }
}
