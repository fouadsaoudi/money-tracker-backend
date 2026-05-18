<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Currency;
use App\Models\User;

class UserSetupService
{
    /**
     * @var list<array{name:string,color:?string,icon:?string}>
     */
    private const STARTER_CATEGORIES = [
        ['name' => 'Salary', 'color' => '#15803d', 'icon' => 'wallet'],
        ['name' => 'Food', 'color' => '#ea580c', 'icon' => 'utensils'],
        ['name' => 'Transport', 'color' => '#2563eb', 'icon' => 'car'],
        ['name' => 'Bills', 'color' => '#dc2626', 'icon' => 'receipt'],
        ['name' => 'Shopping', 'color' => '#7c3aed', 'icon' => 'bag'],
        ['name' => 'Health', 'color' => '#db2777', 'icon' => 'heart'],
        ['name' => 'Savings', 'color' => '#0f766e', 'icon' => 'piggy-bank'],
        ['name' => 'Other', 'color' => '#4b5563', 'icon' => 'shapes'],
    ];

    public function initialize(User $user): void
    {
        $defaultCurrency = Currency::query()->where('code', 'USD')->first();

        if ($defaultCurrency !== null && $user->reporting_currency_id === null) {
            $user->forceFill([
                'reporting_currency_id' => $defaultCurrency->id,
            ])->save();
        }

        if ($defaultCurrency !== null) {
            $user->wallets()->firstOrCreate(
                [
                    'currency_id' => $defaultCurrency->id,
                ],
                [
                    'name' => $defaultCurrency->code.' Wallet',
                    'balance' => 0,
                    'is_default' => true,
                ]
            );
        }

        foreach (self::STARTER_CATEGORIES as $starterCategory) {
            $user->categories()->firstOrCreate(
                [
                    'name' => $starterCategory['name'],
                ],
                [
                    'color' => $starterCategory['color'],
                    'icon' => $starterCategory['icon'],
                    'is_archived' => false,
                ]
            );
        }
    }
}
