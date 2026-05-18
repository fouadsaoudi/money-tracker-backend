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
        Schema::create('wallet_conversions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('destination_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('source_transaction_id')->unique()->constrained('transactions')->restrictOnDelete();
            $table->foreignId('destination_transaction_id')->unique()->constrained('transactions')->restrictOnDelete();
            $table->decimal('source_amount', 20, 4);
            $table->decimal('destination_amount', 20, 4);
            $table->dateTime('occurred_on');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_conversions');
    }
};
