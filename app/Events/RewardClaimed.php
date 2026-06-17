<?php

namespace App\Events;

use App\Models\RewardClaim;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RewardClaimed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public RewardClaim $rewardClaim
    ) {}
}
