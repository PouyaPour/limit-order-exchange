<?php

namespace App\Http\Requests\Api\V1\Order;

use App\Enums\OrderSideEnum;
use App\Enums\SymbolEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'symbol' => [
                'required',
                'string',
                Rule::enum(SymbolEnum::class),
            ],
            'side' => [
                'required',
                'string',
                Rule::enum(OrderSideEnum::class),
            ],
            'price' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,8'
            ],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,8',
            ],
        ];
    }
}
