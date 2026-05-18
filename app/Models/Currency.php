<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'decimal_places' => 'integer',
        ];
    }

    /**
     * @return HasMany<ExchangeRate, $this>
     */
    public function outgoingExchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency_id');
    }

    /**
     * @return HasMany<ExchangeRate, $this>
     */
    public function incomingExchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'to_currency_id');
    }

    /**
     * @return HasMany<Wallet, $this>
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
