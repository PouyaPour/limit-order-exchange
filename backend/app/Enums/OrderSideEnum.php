<?php

namespace App\Enums;

enum OrderSideEnum: string
{
    use EnumTrait;
    case BUY = 'buy';
    case SELL = 'sell';

    public function label(): string
    {
        return match($this) {
            self::BUY => __('enum.order_side.buy'),
            self::SELL => __('enum.order_side.sell')
        };
    }
}
