<?php

namespace App\Http\Requests;

class UpdateExchangeRateRequest extends StoreExchangeRateRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach ($rules as $key => $ruleSet) {
            array_unshift($ruleSet, 'sometimes');
            $rules[$key] = $ruleSet;
        }

        return $rules;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'from_currency_id' => [
                'description' => 'Updated source currency ID.',
                'example' => 1,
            ],
            'to_currency_id' => [
                'description' => 'Updated target currency ID.',
                'example' => 2,
            ],
            'rate' => [
                'description' => 'Updated conversion rate.',
                'example' => 90000,
            ],
            'effective_at' => [
                'description' => 'Updated effective timestamp for the rate.',
                'example' => '2026-05-11 09:30:00',
            ],
        ];
    }
}
