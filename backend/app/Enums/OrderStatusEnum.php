<?php

namespace App\Enums;

enum OrderStatusEnum: int
{
    use EnumTrait;
    case OPEN = 1;
    case FILLED = 2;
    case CANCELLED = 3;

    public function label(): string
    {
        return match($this) {
            self::OPEN => __('enum.order_status.' . self::OPEN->value),
            self::FILLED => __('enum.order_status.' . self::FILLED->value),
            self::CANCELLED => __('enum.order_status.' . self::CANCELLED->value),
        };
    }
}
