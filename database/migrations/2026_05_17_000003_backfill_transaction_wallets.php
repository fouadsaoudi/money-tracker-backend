<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $transactions = DB::table('transactions')
            ->whereNull('wallet_id')
            ->orderBy('id')
            ->get();

        foreach ($transactions as $transaction) {
            $walletId = DB::table('wallets')
                ->where('user_id', $transaction->user_id)
                ->where('currency_id', $transaction->currency_id)
                ->value('id');

            if ($walletId === null) {
                $currency = DB::table('currencies')->find($transaction->currency_id);
                $baseName = ($currency?->code ?? 'Currency').' Wallet';
                $name = $baseName;
                $suffix = 2;

                while (DB::table('wallets')
                    ->where('user_id', $transaction->user_id)
                    ->where('name', $name)
                    ->exists()
                ) {
                    $name = $baseName.' '.$suffix;
                    $suffix++;
                }

                $now = now();
                $walletId = DB::table('wallets')->insertGetId([
                    'user_id' => $transaction->user_id,
                    'currency_id' => $transaction->currency_id,
                    'name' => $name,
                    'balance' => '0.0000',
                    'is_default' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update(['wallet_id' => $walletId]);

            if ($transaction->deleted_at !== null) {
                continue;
            }

            $wallet = DB::table('wallets')->where('id', $walletId)->first();
            $delta = $transaction->type === 'outgoing'
                ? bcmul((string) $transaction->amount, '-1', 4)
                : bcadd((string) $transaction->amount, '0', 4);

            DB::table('wallets')
                ->where('id', $walletId)
                ->update([
                    'balance' => bcadd((string) $wallet->balance, $delta, 4),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('transactions')->update(['wallet_id' => null]);
    }
};
