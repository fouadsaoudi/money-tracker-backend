<?php

namespace App\Http\Requests;

use App\Models\Wallet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWalletRequest extends FormRequest
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
        /** @var Wallet|null $wallet */
        $wallet = $this->route('wallet');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('wallets', 'name')
                    ->ignore($wallet?->id)
                    ->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'balance' => ['sometimes', 'numeric'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
