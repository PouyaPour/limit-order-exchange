<?php

namespace App\Models;

use App\Enums\OrderSideEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\SymbolEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'symbol',
        'side',
        'price',
        'amount',
        'status',
        'locked_balance',
    ];

    protected function casts(): array
    {
        return [
            'symbol' => SymbolEnum::class,
            'side' => OrderSideEnum::class,
            'price' => 'decimal:8',
            'amount' => 'decimal:8',
            'locked_balance' => 'decimal:8',
            'status' => OrderStatusEnum::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isBuy(): bool
    {
        return $this->side->isBuy();
    }

    public function isSell(): bool
    {
        return $this->side->isSell();
    }
}
