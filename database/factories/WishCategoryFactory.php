<?php

namespace Database\Factories;

use App\Models\WishCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WishCategoryFactory extends Factory
{
    protected $model = WishCategory::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();
        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
        ];
    }
}
