<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletConversion extends Model
{
    protected $fillable = [
        'user_id',
        'source_wallet_id',
        'destination_wallet_id',
        'source_transaction_id',
        'destination_transaction_id',
        'source_amount',
        'destination_amount',
        'occurred_on',
    ];

    protected function casts(): array
    {
        return [
            'source_amount' => 'decimal:4',
            'destination_amount' => 'decimal:4',
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
     * @return BelongsTo<Wallet, $this>
     */
    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'destination_wallet_id');
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function sourceTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'source_transaction_id');
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function destinationTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'destination_transaction_id');
    }
}
