<?php

namespace Database\Factories;

use App\Enums\SymbolEnum;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symbol' => $this->faker->randomElement(SymbolEnum::values()),
            'amount' => $this->faker->randomFloat(8, 0, 100),
            'locked_amount' => $this->faker->randomFloat(8, 0, 50),
        ];
    }
}
