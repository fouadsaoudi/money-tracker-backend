<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\User;
use App\Services\UserSetupService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'reporting_currency_id' => Currency::query()->where('code', 'USD')->value('id'),
        ]);

        app(UserSetupService::class)->initialize($user);
    }
}
