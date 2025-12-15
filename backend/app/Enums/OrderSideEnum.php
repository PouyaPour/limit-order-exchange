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

    public function opposite(): self
    {
        return match($this) {
            self::BUY => self::SELL,
            self::SELL => self::BUY,
        };
    }

    public function isBuy(): bool
    {
        return $this === self::BUY;
    }

    public function isSell(): bool
    {
        return $this === self::SELL;
    }
}
