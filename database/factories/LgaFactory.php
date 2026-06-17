<?php

namespace Database\Factories;

use App\Models\Lga;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

class LgaFactory extends Factory
{
    protected $model = Lga::class;

    public function definition(): array
    {
        return [
            'state_id' => State::factory(),
            'name' => $this->faker->city(),
        ];
    }
}
