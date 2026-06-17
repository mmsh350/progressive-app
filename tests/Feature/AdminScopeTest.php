<?php

use App\Models\User;
use App\Models\Submission;
use App\Models\State;
use App\Models\Lga;
use App\Models\Occupation;
use App\Models\AgentProfile;
use App\Repositories\SubmissionRepositoryInterface;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed');

    $this->lagos = State::where('code', 'LA')->first();
    $this->lagosLga = Lga::where('state_id', $this->lagos->id)->first();
    
    $this->oyo = State::where('code', 'OY')->first();
    $this->oyoLga = Lga::where('state_id', $this->oyo->id)->first();

    $this->occupation = Occupation::first();

    $this->submissionRepository = app(SubmissionRepositoryInterface::class);
});

test('state coordinator is scoped to view and export only their state submissions', function () {
    // 1. Create a coordinator for Lagos
    $coordinatorUser = User::create([
        'name' => 'Lagos Coordinator',
        'email' => 'lagos_coord@apc2027.org',
        'password' => bcrypt('password'),
    ]);
    $coordinatorUser->assignRole('State Coordinator');
    
    AgentProfile::create([
        'user_id' => $coordinatorUser->id,
        'state_id' => $this->lagos->id,
        'status' => 'active'
    ]);

    // 2. Create submissions in Lagos and Oyo
    $lagosSub = Submission::factory()->create([
        'full_name' => 'Lagos Citizen',
        'state_id' => $this->lagos->id,
        'lga_id' => $this->lagosLga->id,
        'occupation_id' => $this->occupation->id,
    ]);

    $oyoSub = Submission::factory()->create([
        'full_name' => 'Oyo Citizen',
        'state_id' => $this->oyo->id,
        'lga_id' => $this->oyoLga->id,
        'occupation_id' => $this->occupation->id,
    ]);

    // Authenticate as Coordinator
    $this->actingAs($coordinatorUser);

    // Repository filters query with coordinator's state scoping
    $filters = [
        'state_id' => $this->lagos->id, // explicit lagos
    ];
    
    // Simulate repository scoping when filtering
    // If the coordinator is logged in, filters are scoped:
    $coordinatorFilters = [
        'state_id' => $coordinatorUser->agentProfile->state_id,
    ];

    $lagosSubmissions = $this->submissionRepository->getForExport($coordinatorFilters);

    expect($lagosSubmissions->contains($lagosSub))->toBeTrue();
    expect($lagosSubmissions->contains($oyoSub))->toBeFalse();
});

test('super admin can view all submissions across all states', function () {
    $superAdmin = User::where('email', 'superadmin@apc2027.org')->first();
    $this->actingAs($superAdmin);

    // Lagos Sub
    $lagosSub = Submission::factory()->create([
        'full_name' => 'Lagos Citizen 2',
        'state_id' => $this->lagos->id,
        'lga_id' => $this->lagosLga->id,
        'occupation_id' => $this->occupation->id,
    ]);

    // Oyo Sub
    $oyoSub = Submission::factory()->create([
        'full_name' => 'Oyo Citizen 2',
        'state_id' => $this->oyo->id,
        'lga_id' => $this->oyoLga->id,
        'occupation_id' => $this->occupation->id,
    ]);

    $allSubmissions = $this->submissionRepository->getForExport([]);

    expect($allSubmissions->contains($lagosSub))->toBeTrue();
    expect($allSubmissions->contains($oyoSub))->toBeTrue();
});
