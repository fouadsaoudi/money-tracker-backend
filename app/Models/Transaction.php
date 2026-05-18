<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'wallet_id',
        'currency_id',
        'type',
        'amount',
        'note',
        'occurred_on',
        'reporting_currency_id',
        'exchange_rate_snapshot',
        'converted_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'exchange_rate_snapshot' => 'decimal:8',
            'converted_amount' => 'decimal:4',
            'occurred_on' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function reportingCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'reporting_currency_id');
    }

    /**
     * @return HasOne<GoalContribution, $this>
     */
    public function goalContribution(): HasOne
    {
        return $this->hasOne(GoalContribution::class);
    }

    /**
     * @return HasOne<WalletConversion, $this>
     */
    public function sourceConversion(): HasOne
    {
        return $this->hasOne(WalletConversion::class, 'source_transaction_id');
    }

    /**
     * @return HasOne<WalletConversion, $this>
     */
    public function destinationConversion(): HasOne
    {
        return $this->hasOne(WalletConversion::class, 'destination_transaction_id');
    }
}
