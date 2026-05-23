<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_invoice_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();

            $table->index('transaction_id');
        });

        DB::table('transactions')
            ->whereNotNull('invoice_image_path')
            ->orderBy('id')
            ->select(['id', 'invoice_image_path', 'created_at', 'updated_at'])
            ->chunkById(100, function ($transactions): void {
                foreach ($transactions as $transaction) {
                    DB::table('transaction_invoice_images')->insert([
                        'transaction_id' => $transaction->id,
                        'path' => $transaction->invoice_image_path,
                        'created_at' => $transaction->created_at ?? now(),
                        'updated_at' => $transaction->updated_at ?? now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_invoice_images');
    }
};
