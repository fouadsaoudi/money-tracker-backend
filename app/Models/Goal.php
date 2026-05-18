<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    protected $fillable = [
        'user_id',
        'currency_id',
        'name',
        'target_amount',
        'note',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:4',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return HasMany<GoalContribution, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class);
    }
}
