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
        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('wallet_id')
                ->nullable()
                ->after('category_id')
                ->constrained()
                ->restrictOnDelete();

            $table->index(['user_id', 'wallet_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'wallet_id']);
            $table->dropConstrainedForeignId('wallet_id');
        });
    }
};
