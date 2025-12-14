<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 10);
            $table->string('side');
            $table->decimal('price', 20, 8);
            $table->decimal('amount', 20, 8);
            $table->tinyInteger('status')->default(1);
            $table->decimal('locked_balance', 20, 8)->default(0);
            $table->timestamps();

            $table->index(['symbol', 'status', 'side', 'price']);
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_price_positive CHECK (price > 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_amount_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_locked_balance_positive CHECK (locked_balance >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
