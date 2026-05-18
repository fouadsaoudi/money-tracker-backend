<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Goal
 */
class GoalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentAmount = (string) ($this->current_amount ?? $this->contributions()->sum('amount'));
        $remainingAmount = max(0, (float) $this->target_amount - (float) $currentAmount);
        $progress = (float) $this->target_amount > 0
            ? min(1, (float) $currentAmount / (float) $this->target_amount)
            : 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'target_amount' => $this->target_amount,
            'current_amount' => number_format((float) $currentAmount, 4, '.', ''),
            'remaining_amount' => number_format($remainingAmount, 4, '.', ''),
            'progress' => round($progress, 4),
            'contributions_count' => $this->contributions_count ?? $this->contributions()->count(),
            'note' => $this->note,
            'completed_at' => $this->completed_at?->toISOString(),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'recent_contributions' => GoalContributionResource::collection(
                $this->whenLoaded('contributions')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
