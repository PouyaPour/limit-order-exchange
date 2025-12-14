<?php

namespace App\Enums;

trait EnumTrait
{
    public static function values(): array
    {
        return array_map(fn($enum) => $enum->value, self::cases());
    }
}
