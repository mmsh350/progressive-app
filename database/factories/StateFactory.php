<?php

namespace Database\Factories;

use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

class StateFactory extends Factory
{
    protected $model = State::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->state(),
            'code' => strtoupper($this->faker->unique()->lexify('??')),
        ];
    }
}
