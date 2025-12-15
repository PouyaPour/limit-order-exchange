<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('symbol', 10);
            $table->decimal('amount', 20, 8)->default(0);
            $table->decimal('locked_amount', 20, 8)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'symbol']);
        });

        $driver = DB::getDriverName();
        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE assets ADD CONSTRAINT assets_amount_positive CHECK (amount >= 0)');
            DB::statement('ALTER TABLE assets ADD CONSTRAINT assets_locked_amount_positive CHECK (locked_amount >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
