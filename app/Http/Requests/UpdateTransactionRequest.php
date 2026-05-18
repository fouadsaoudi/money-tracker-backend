<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
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
            'category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query
                        ->where('user_id', $this->user()->id)
                        ->where(function ($query): void {
                            $query->where('is_archived', false);

                            $category = $this->route('transaction')?->category_id;

                            if ($category !== null) {
                                $query->orWhere('id', $category);
                            }
                        })
                ),
            ],
            'currency_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where('is_active', true),
            ],
            'wallet_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('wallets', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
            'type' => ['sometimes', 'required', Rule::in(['incoming', 'outgoing'])],
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'note' => ['sometimes', 'nullable', 'string'],
            'occurred_on' => ['sometimes', 'required', 'date'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'category_id' => [
                'description' => 'Updated category ID.',
                'example' => 4,
            ],
            'currency_id' => [
                'description' => 'Deprecated. The transaction currency is now taken from wallet_id.',
                'example' => 1,
            ],
            'wallet_id' => [
                'description' => 'Updated wallet ID.',
                'example' => 1,
            ],
            'type' => [
                'description' => 'Updated transaction type.',
                'example' => 'incoming',
            ],
            'amount' => [
                'description' => 'Updated positive native amount.',
                'example' => 125.75,
            ],
            'note' => [
                'description' => 'Updated optional note.',
                'example' => 'Freelance payment received',
            ],
            'occurred_on' => [
                'description' => 'Updated transaction timestamp.',
                'example' => '2026-05-11 10:15:00',
            ],
        ];
    }
}
