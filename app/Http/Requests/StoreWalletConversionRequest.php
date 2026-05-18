<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWalletConversionRequest extends FormRequest
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
            'source_wallet_id' => [
                'required',
                'integer',
                Rule::exists('wallets', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
            'destination_wallet_id' => [
                'required',
                'integer',
                'different:source_wallet_id',
                Rule::exists('wallets', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
            'source_amount' => ['required', 'numeric', 'gt:0'],
            'destination_amount' => ['required', 'numeric', 'gt:0'],
            'occurred_on' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }
}
