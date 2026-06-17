<?php

namespace App\Listeners;

use App\Events\SubmissionCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendSubmissionConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(SubmissionCreated $event): void
    {
        $submission = $event->submission;

        // Log confirmation as mock SMS / Email gateway
        Log::info("SMS CONFIRMATION SENT to {$submission->phone_number}: Dear {$submission->full_name}, your support declaration has been received successfully. Your tracking reference is {$submission->reference_number}.");

        if ($submission->email) {
            Log::info("EMAIL CONFIRMATION SENT to {$submission->email}: Dear {$submission->full_name}, thank you for registering your support for APC 2027. Tracking Ref: {$submission->reference_number}.");
        }
    }
}
