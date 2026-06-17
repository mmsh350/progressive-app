<?php

namespace Database\Seeders;

use App\Models\Occupation;
use App\Models\WishCategory;
use App\Models\Reward;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SystemSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Occupations
        $occupations = [
            'Student',
            'Civil Servant',
            'Entrepreneur',
            'Farmer',
            'Unemployed',
            'Artisan',
            'Professional (Corporate)',
            'Other',
        ];

        foreach ($occupations as $name) {
            Occupation::create([
                'name' => $name,
                'is_active' => true,
            ]);
        }

        // 2. Seed Wish Categories
        $categories = [
            ['name' => 'Agriculture', 'description' => 'Farming inputs, grants, and agricultural mechanization support.'],
            ['name' => 'Education', 'description' => 'Scholarships, learning aid, and vocational training requests.'],
            ['name' => 'Youth Empowerment', 'description' => 'Skill acquisition, technology grants, and employment assistance.'],
            ['name' => 'Business Support', 'description' => 'Micro-loans, SME grants, and business advisory services.'],
            ['name' => 'Housing', 'description' => 'Affordable housing schemes and shelter assistance programs.'],
            ['name' => 'Healthcare', 'description' => 'Health insurance coverage, medical aid, and subsidized drugs.'],
        ];

        foreach ($categories as $cat) {
            WishCategory::create([
                'name' => $cat['name'],
                'slug' => Str::slug($cat['name']),
                'description' => $cat['description'],
            ]);
        }

        // 3. Seed Rewards (Probabilities sum to 100%)
        $rewards = [
            [
                'name' => '₦500 Airtime',
                'type' => 'airtime',
                'value' => 500.00,
                'probability' => 20.00,
                'daily_limit' => 100,
                'inventory_limit' => 1000,
            ],
            [
                'name' => '₦1000 Airtime',
                'type' => 'airtime',
                'value' => 1000.00,
                'probability' => 10.00,
                'daily_limit' => 50,
                'inventory_limit' => 500,
            ],
            [
                'name' => '₦2000 Airtime',
                'type' => 'airtime',
                'value' => 2000.00,
                'probability' => 5.00,
                'daily_limit' => 10,
                'inventory_limit' => 100,
            ],
            [
                'name' => '5GB Data',
                'type' => 'data',
                'value' => 5.00,
                'probability' => 15.00,
                'daily_limit' => 100,
                'inventory_limit' => 1000,
            ],
            [
                'name' => '10GB Data',
                'type' => 'data',
                'value' => 10.00,
                'probability' => 5.00,
                'daily_limit' => 50,
                'inventory_limit' => 500,
            ],
            [
                'name' => 'No Reward',
                'type' => 'none',
                'value' => 0.00,
                'probability' => 45.00,
                'daily_limit' => 0,
                'inventory_limit' => 0,
            ],
        ];

        foreach ($rewards as $reward) {
            Reward::create($reward);
        }

        // 4. Seed default system settings
        SystemSetting::create([
            'key' => 'spin_enabled',
            'value' => 'true',
            'type' => 'boolean',
        ]);

        SystemSetting::create([
            'key' => 'max_daily_spins_per_ip',
            'value' => '3',
            'type' => 'integer',
        ]);
    }
}
