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
            'invoice_image' => ['sometimes', 'nullable', 'image', 'max:5120'],
            'invoice_images' => ['sometimes', 'nullable', 'array', 'max:8'],
            'invoice_images.*' => ['image', 'max:5120'],
            'remove_invoice_image' => ['sometimes', 'boolean'],
            'remove_invoice_images' => ['sometimes', 'boolean'],
            'remove_invoice_image_ids' => ['sometimes', 'array'],
            'remove_invoice_image_ids.*' => ['integer'],
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
            'invoice_image' => [
                'description' => 'Additional invoice or receipt image. Deprecated; use invoice_images[] for multiple files.',
                'example' => null,
            ],
            'invoice_images' => [
                'description' => 'Additional invoice or receipt images.',
                'example' => null,
            ],
            'remove_invoice_image' => [
                'description' => 'Set true to remove current invoice images. Deprecated; use remove_invoice_images.',
                'example' => false,
            ],
            'remove_invoice_images' => [
                'description' => 'Set true to remove current invoice images.',
                'example' => false,
            ],
            'remove_invoice_image_ids' => [
                'description' => 'Specific invoice image IDs to remove.',
                'example' => [3],
            ],
            'occurred_on' => [
                'description' => 'Updated transaction timestamp.',
                'example' => '2026-05-11 10:15:00',
            ],
        ];
    }
}
