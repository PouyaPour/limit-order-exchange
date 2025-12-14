<?php

namespace App\Enums;

enum SymbolEnum: string
{
    use EnumTrait;

    case BTC = 'BTC';
    case ETH = 'ETH';

    public function label(): string
    {
        return match($this) {
            self::BTC => __('enum.symbol.btc'),
            self::ETH => __('enum.symbol.eth'),
        };
    }
}
