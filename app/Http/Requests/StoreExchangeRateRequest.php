<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from_currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where('is_active', true),
                'different:to_currency_id',
            ],
            'to_currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where('is_active', true),
                'different:from_currency_id',
            ],
            'rate' => ['required', 'numeric', 'gt:0'],
            'effective_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'from_currency_id' => [
                'description' => 'The source currency ID.',
                'example' => 1,
            ],
            'to_currency_id' => [
                'description' => 'The target currency ID.',
                'example' => 2,
            ],
            'rate' => [
                'description' => 'Conversion rate from the source currency into the target currency.',
                'example' => 89500.25,
            ],
            'effective_at' => [
                'description' => 'Timestamp from which this rate becomes valid. Defaults to now when omitted.',
                'example' => '2026-05-10 12:00:00',
            ],
        ];
    }
}
