<?php

use App\Models\State;
use App\Models\Lga;
use App\Models\Occupation;
use App\Models\WishCategory;
use App\Models\Submission;
use App\Models\Reward;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Artisan::call('db:seed');

    $this->state = State::where('code', 'LA')->first();
    $this->lga = Lga::where('state_id', $this->state->id)->first();
    $this->occupation = Occupation::first();
    $this->wishCategory = WishCategory::first();
});

test('it gets list of states and lgas', function () {
    $response = $this->getJson('/api/v1/states');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'name', 'code']
        ]
    ]);

    $responseLgas = $this->getJson("/api/v1/lgas?state_id={$this->state->id}");
    $responseLgas->assertStatus(200);
    $responseLgas->assertJsonStructure([
        'data' => [
            '*' => ['id', 'state_id', 'name']
        ]
    ]);
});

test('it handles submit and tracking status via JSON API', function () {
    $file = UploadedFile::fake()->image('pvc_card.jpg');

    $data = [
        'full_name' => 'John Doe API',
        'phone_number' => '09012345678',
        'email' => 'johndoeapi@gmail.com',
        'gender' => 'male',
        'age_group' => '18-25',
        'state_id' => $this->state->id,
        'lga_id' => $this->lga->id,
        'voted_2023' => false,
        'vote_2027' => true,
        'occupation_id' => $this->occupation->id,
        'wish_category_id' => $this->wishCategory->id,
        'wish_title' => 'API Wish',
        'wish_description' => 'A clear request via API',
        'pvc_selfie' => $file,
        'agreement' => true,
    ];

    $response = $this->postJson('/api/v1/submit', $data);

    $response->assertStatus(201);
    $response->assertJsonStructure(['message', 'reference_number', 'submission_id']);

    $ref = $response->json('reference_number');

    // Track status via API
    $statusResponse = $this->getJson("/api/v1/status/{$ref}");
    $statusResponse->assertStatus(200);
    $statusResponse->assertJsonStructure([
        'data' => [
            'reference_number', 'full_name', 'status', 'state', 'lga'
        ]
    ]);
});
