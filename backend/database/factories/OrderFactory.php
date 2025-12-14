<?php

namespace Database\Factories;

use App\Enums\OrderSideEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\SymbolEnum;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symbol' => $this->faker->randomElement(SymbolEnum::values()),
            'side' => $this->faker->randomElement([OrderSideEnum::BUY, OrderSideEnum::SELL]),
            'price' => $this->faker->randomFloat(8, 1, 1000),
            'amount' => $this->faker->randomFloat(8, 0.01, 100),
            'status' => $this->faker->randomElement(OrderStatusEnum::values()),
            'locked_balance' => $this->faker->randomFloat(8, 0, 500),
        ];
    }

    public function buy(): static
    {
        return $this->state(fn (array $attributes) => [
            'side' => OrderSideEnum::BUY,
        ]);
    }

    public function sell(): static
    {
        return $this->state(fn (array $attributes) => [
            'side' => OrderSideEnum::SELL,
        ]);
    }
}
