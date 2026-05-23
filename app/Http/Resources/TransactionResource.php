<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invoiceImages = $this->invoiceImages
            ->filter(fn ($image): bool => $image->path !== null)
            ->map(fn ($image): array => [
                'id' => $image->id,
                'url' => $request->getSchemeAndHttpHost().'/storage/'.$image->path,
            ])
            ->values();
        $invoiceImageUrls = $invoiceImages->pluck('url')->values();

        if ($invoiceImageUrls->isEmpty() && $this->invoice_image_path !== null) {
            $invoiceImageUrls->push($request->getSchemeAndHttpHost().'/storage/'.$this->invoice_image_path);
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'note' => $this->note,
            'invoice_image_url' => $invoiceImageUrls->first(),
            'invoice_image_urls' => $invoiceImageUrls,
            'invoice_images' => $invoiceImages,
            'occurred_on' => $this->occurred_on?->toISOString(),
            'exchange_rate_snapshot' => $this->exchange_rate_snapshot,
            'converted_amount' => $this->converted_amount,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'wallet' => new WalletResource($this->whenLoaded('wallet')),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'reporting_currency' => new CurrencyResource($this->whenLoaded('reportingCurrency')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
