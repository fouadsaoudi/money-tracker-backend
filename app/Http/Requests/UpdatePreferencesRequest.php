<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
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
            'reporting_currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'reporting_currency_id' => [
                'description' => 'The currency ID used for combined balances and reporting totals.',
                'example' => 1,
            ],
        ];
    }
}
