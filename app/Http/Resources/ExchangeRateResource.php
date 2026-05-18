<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ExchangeRate
 */
class ExchangeRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rate' => $this->rate,
            'effective_at' => $this->effective_at?->toISOString(),
            'from_currency' => new CurrencyResource($this->whenLoaded('fromCurrency')),
            'to_currency' => new CurrencyResource($this->whenLoaded('toCurrency')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
