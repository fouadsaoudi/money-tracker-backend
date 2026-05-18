<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->enum('type', ['incoming', 'outgoing']);
            $table->decimal('amount', 20, 4);
            $table->text('note')->nullable();
            $table->dateTime('occurred_on');
            $table->foreignId('reporting_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate_snapshot', 20, 8);
            $table->decimal('converted_amount', 20, 4);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'occurred_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
