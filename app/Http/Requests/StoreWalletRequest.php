<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWalletRequest extends FormRequest
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
            'name' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('wallets', 'name')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
            'currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where('is_active', true),
                Rule::unique('wallets', 'currency_id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
            'balance' => ['nullable', 'numeric'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
