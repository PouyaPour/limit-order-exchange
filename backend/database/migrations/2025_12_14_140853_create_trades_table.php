<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buy_order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('sell_order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('symbol', 10);
            $table->decimal('price', 20, 8);
            $table->decimal('amount', 20, 8);
            $table->decimal('total_value', 20, 8);
            $table->decimal('commission', 20, 8);
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index(['symbol', 'executed_at']);
        });

        DB::statement('ALTER TABLE trades ADD CONSTRAINT trades_price_positive CHECK (price > 0)');
        DB::statement('ALTER TABLE trades ADD CONSTRAINT trades_amount_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE trades ADD CONSTRAINT trades_total_value_positive CHECK (total_value > 0)');
        DB::statement('ALTER TABLE trades ADD CONSTRAINT trades_commission_positive CHECK (commission >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
