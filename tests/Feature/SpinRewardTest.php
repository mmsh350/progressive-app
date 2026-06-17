<?php

use App\Models\Submission;
use App\Models\Reward;
use App\Models\Spin;
use App\Models\RewardClaim;
use App\Models\SystemSetting;
use App\Models\State;
use App\Models\Lga;
use App\Models\Occupation;
use App\Services\RewardEngineService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Artisan::call('db:seed');

    $this->state = State::where('code', 'LA')->first();
    $this->lga = Lga::where('state_id', $this->state->id)->first();
    $this->occupation = Occupation::first();

    $this->rewardEngine = app(RewardEngineService::class);
});

test('it draws a spin successfully and logs spin and reward claim', function () {
    $submission = Submission::factory()->create([
        'state_id' => $this->state->id,
        'lga_id' => $this->lga->id,
        'occupation_id' => $this->occupation->id,
    ]);

    $ipAddress = '192.168.1.1';
    
    // Clear existing spins for this IP to ensure no limit hit
    DB::table('spins')->where('ip_address', $ipAddress)->delete();

    $reward = $this->rewardEngine->spin($submission->id, $ipAddress);

    expect($reward)->toBeInstanceOf(Reward::class);

    $this->assertDatabaseHas('spins', [
        'submission_id' => $submission->id,
        'reward_id' => $reward->id,
        'ip_address' => $ipAddress,
    ]);

    if ($reward->type !== 'none') {
        $this->assertDatabaseHas('reward_claims', [
            'submission_id' => $submission->id,
            'reward_id' => $reward->id,
            'status' => 'processed',
        ]);
    }
});

test('it enforces daily IP spin limits', function () {
    $ipAddress = '192.168.1.5';
    SystemSetting::where('key', 'max_daily_spins_per_ip')->update(['value' => '2']);

    // Create 2 prior spins from different submissions today for this IP
    for ($i = 0; $i < 2; $i++) {
        $sub = Submission::factory()->create([
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'occupation_id' => $this->occupation->id,
        ]);
        $reward = Reward::where('type', 'none')->first();
        Spin::create([
            'submission_id' => $sub->id,
            'reward_id' => $reward->id,
            'ip_address' => $ipAddress,
        ]);
    }

    // A 3rd spin on the same IP must throw an exception
    $sub3 = Submission::factory()->create([
        'state_id' => $this->state->id,
        'lga_id' => $this->lga->id,
        'occupation_id' => $this->occupation->id,
    ]);

    expect(fn() => $this->rewardEngine->spin($sub3->id, $ipAddress))
        ->toThrow(\Exception::class, 'Daily IP address spin limit exceeded.');
});

test('it falls back to No Reward when reward daily limit is hit', function () {
    $reward = Reward::where('type', 'airtime')->first();
    $reward->update([
        'daily_limit' => 1,
        'probability' => 100.00 // Force this reward
    ]);

    // Deactivate other rewards to guarantee selection or set their probability to 0
    Reward::where('id', '!=', $reward->id)->where('type', '!=', 'none')->update(['probability' => 0]);
    Reward::where('type', 'none')->update(['probability' => 0]);

    // First spin should win the reward
    $sub1 = Submission::factory()->create([
        'state_id' => $this->state->id,
        'lga_id' => $this->lga->id,
        'occupation_id' => $this->occupation->id,
    ]);
    
    $wonReward = $this->rewardEngine->spin($sub1->id, '192.168.2.1');
    expect($wonReward->id)->toBe($reward->id);

    // Second spin should select it, but fall back to No Reward because daily limit is hit
    $sub2 = Submission::factory()->create([
        'state_id' => $this->state->id,
        'lga_id' => $this->lga->id,
        'occupation_id' => $this->occupation->id,
    ]);

    $wonReward2 = $this->rewardEngine->spin($sub2->id, '192.168.2.2');
    expect($wonReward2->type)->toBe('none');
});
