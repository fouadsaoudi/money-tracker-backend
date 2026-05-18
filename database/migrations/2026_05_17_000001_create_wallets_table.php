<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('name', 100);
            $table->decimal('balance', 16, 4)->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'currency_id']);
            $table->unique(['user_id', 'name']);
        });

        $usdId = DB::table('currencies')->where('code', 'USD')->value('id');

        if ($usdId !== null) {
            $now = now();

            DB::table('users')
                ->select('id')
                ->orderBy('id')
                ->each(function (object $user) use ($usdId, $now): void {
                    DB::table('wallets')->insertOrIgnore([
                        'user_id' => $user->id,
                        'currency_id' => $usdId,
                        'name' => 'USD Wallet',
                        'balance' => '0.0000',
                        'is_default' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
