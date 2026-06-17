<?php

use App\Models\Submission;
use App\Models\State;
use App\Models\Lga;
use App\Models\Occupation;
use App\Models\WishCategory;
use App\Models\Reward;
use App\Models\User;
use App\Models\AgentProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Actions\ProcessSubmissionAction;
use App\Actions\ProcessSpinAction;

beforeEach(function () {
    Storage::fake('local');
    
    // Seed database dependencies (roles, permissions, states, LGAs, occupations, rewards)
    \Illuminate\Support\Facades\Artisan::call('db:seed');
    
    $this->state = State::where('code', 'LA')->first();
    $this->lga = Lga::where('state_id', $this->state->id)->first();
    $this->occupation = Occupation::first();
    $this->wishCategory = WishCategory::first();
    
    $this->reward = Reward::where('type', 'airtime')->first();
});

test('it validates submission input correctly', function () {
    $response = $this->postJson('/api/v1/submit', []);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors([
        'full_name', 'phone_number', 'gender', 'age_group', 'state_id', 
        'lga_id', 'occupation_id', 'wish_category_id', 'wish_title', 
        'wish_description', 'pvc_selfie', 'agreement'
    ]);
});

test('it processes voluntary support declaration successfully', function () {
    $file = UploadedFile::fake()->image('pvc_selfie.jpg');

    $data = [
        'full_name' => 'Adebayo Tunde',
        'phone_number' => '08012345678',
        'email' => 'tunde@gmail.com',
        'gender' => 'male',
        'age_group' => '26-35',
        'state_id' => $this->state->id,
        'lga_id' => $this->lga->id,
        'voted_2023' => true,
        'vote_2027' => true,
        'occupation_id' => $this->occupation->id,
        'wish_category_id' => $this->wishCategory->id,
        'wish_title' => 'Tractor Supply',
        'wish_description' => 'Need a tractor for farming group',
        'pvc_selfie' => $file,
        'agreement' => true,
    ];

    $response = $this->postJson('/api/v1/submit', $data);

    $response->assertStatus(201);
    $response->assertJsonStructure(['message', 'reference_number', 'submission_id']);

    $ref = $response->json('reference_number');
    expect($ref)->toStartWith('APC-2027-');

    $this->assertDatabaseHas('submissions', [
        'full_name' => 'Adebayo Tunde',
        'reference_number' => $ref,
    ]);

    $this->assertDatabaseHas('wishes', [
        'title' => 'Tractor Supply',
    ]);
});

test('it enforces 1 spin limit per submission', function () {
    $submission = Submission::factory()->create([
        'state_id' => $this->state->id,
        'lga_id' => $this->lga->id,
        'occupation_id' => $this->occupation->id,
    ]);

    $response = $this->postJson('/api/v1/spin', [
        'submission_id' => $submission->id,
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
    ]);

    // Second spin must fail
    $response2 = $this->postJson('/api/v1/spin', [
        'submission_id' => $submission->id,
    ]);
    
    $response2->assertStatus(422);
    $response2->assertJson([
        'success' => false,
    ]);
});

test('it scopes data views for state coordinator access', function () {
    // Coordinator for Lagos
    $coordUser = User::create([
        'name' => 'Lagos Coord',
        'email' => 'coord@apc2027.org',
        'password' => bcrypt('password'),
    ]);
    $coordUser->assignRole('State Coordinator');
    AgentProfile::create([
        'user_id' => $coordUser->id,
        'state_id' => $this->state->id,
        'status' => 'active'
    ]);

    // Another state
    $otherState = State::firstOrCreate(['code' => 'OY'], ['name' => 'Oyo']);
    $otherLga = Lga::firstOrCreate(['state_id' => $otherState->id, 'name' => 'Ibadan']);

    $subLagos = Submission::factory()->create([
        'state_id' => $this->state->id,
        'lga_id' => $this->lga->id,
        'occupation_id' => $this->occupation->id,
        'full_name' => 'Lagos Citizen',
    ]);

    $subOyo = Submission::factory()->create([
        'state_id' => $otherState->id,
        'lga_id' => $otherLga->id,
        'occupation_id' => $this->occupation->id,
        'full_name' => 'Oyo Citizen',
    ]);

    // Authenticate coordinator
    $this->actingAs($coordUser);

    // Let's assert policy scoping
    $policy = new \App\Policies\SubmissionPolicy();

    expect($policy->view($coordUser, $subLagos))->toBeTrue();
    expect($policy->view($coordUser, $subOyo))->toBeFalse();
});
