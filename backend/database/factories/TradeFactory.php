<?php

namespace Database\Factories;

use App\Enums\SymbolEnum;
use App\Models\Order;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

class TradeFactory extends Factory
{
    protected $model = Trade::class;

    public function definition(): array
    {
        $price = $this->faker->randomFloat(8, 1000, 50000);
        $amount = $this->faker->randomFloat(8, 0.01, 10);
        $totalValue = $price * $amount;

        return [
            'buy_order_id' => Order::factory(),
            'sell_order_id' => Order::factory(),
            'symbol' => $this->faker->randomElement(SymbolEnum::values()),
            'price' => $price,
            'amount' => $amount,
            'total_value' => $totalValue,
            'commission' => $totalValue * 0.001,
            'executed_at' => $this->faker->dateTimeBetween('-1 month'),
        ];
    }
}
