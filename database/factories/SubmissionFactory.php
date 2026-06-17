<?php

namespace Database\Factories;

use App\Models\Submission;
use App\Models\State;
use App\Models\Lga;
use App\Models\Ward;
use App\Models\Occupation;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    public function definition(): array
    {
        return [
            'reference_number' => 'APC-2027-' . sprintf('%08d', $this->faker->unique()->numberBetween(1, 99999999)),
            'full_name' => $this->faker->name(),
            'phone_number' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'age_group' => $this->faker->randomElement(['18-25', '26-35', '36-50', '50+']),
            'state_id' => State::factory(),
            'lga_id' => function (array $attributes) {
                return Lga::factory()->create(['state_id' => $attributes['state_id']])->id;
            },
            'ward_id' => function (array $attributes) {
                return Ward::factory()->create(['lga_id' => $attributes['lga_id']])->id;
            },
            'polling_unit' => 'Polling Unit ' . $this->faker->numberBetween(1, 100),
            'voted_2023' => $this->faker->boolean(),
            'vote_2027' => $this->faker->boolean(),
            'occupation_id' => Occupation::factory(),
            'status' => 'pending',
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }
}
