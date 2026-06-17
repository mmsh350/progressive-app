<?php

namespace Database\Factories;

use App\Models\Ward;
use App\Models\Lga;
use Illuminate\Database\Eloquent\Factories\Factory;

class WardFactory extends Factory
{
    protected $model = Ward::class;

    public function definition(): array
    {
        return [
            'lga_id' => Lga::factory(),
            'name' => 'Ward ' . $this->faker->word(),
        ];
    }
}
