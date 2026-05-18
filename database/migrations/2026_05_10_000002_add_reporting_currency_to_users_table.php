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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('reporting_currency_id')
                ->nullable()
                ->after('password')
                ->constrained('currencies')
                ->nullOnDelete();
        });

        $usdId = DB::table('currencies')->where('code', 'USD')->value('id');

        if ($usdId !== null) {
            DB::table('users')
                ->whereNull('reporting_currency_id')
                ->update(['reporting_currency_id' => $usdId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reporting_currency_id');
        });
    }
};
