<?php

namespace Database\Factories;

use App\Models\Occupation;
use Illuminate\Database\Eloquent\Factories\Factory;

class OccupationFactory extends Factory
{
    protected $model = Occupation::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->jobTitle(),
            'is_active' => true,
        ];
    }
}
