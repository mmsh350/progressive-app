<?php

namespace App\Actions;

use App\Models\Reward;
use App\Services\RewardEngineService;

class ProcessSpinAction
{
    public function __construct(
        protected RewardEngineService $rewardEngineService
    ) {}

    /**
     * Execute the spin draw for a submission.
     */
    public function execute(int $submissionId, string $ipAddress): Reward
    {
        return $this->rewardEngineService->spin($submissionId, $ipAddress);
    }
}
