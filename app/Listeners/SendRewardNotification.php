<?php

namespace App\Listeners;

use App\Events\RewardClaimed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendRewardNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(RewardClaimed $event): void
    {
        $claim = $event->rewardClaim;
        $submission = $claim->submission;
        $reward = $claim->reward;

        Log::info("SMS REWARD SENT to {$submission->phone_number}: Congratulations! You won {$reward->name}. Use code {$claim->claim_code} to redeem.");

        if ($submission->email) {
            Log::info("EMAIL REWARD SENT to {$submission->email}: Congratulations {$submission->full_name}! You have won a spin reward: {$reward->name}. Redeem Code: {$claim->claim_code}.");
        }

        // Auto-process claim (mock automation)
        $claim->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);
        
        Log::info("Claim ID {$claim->id} auto-processed successfully.");
    }
}
