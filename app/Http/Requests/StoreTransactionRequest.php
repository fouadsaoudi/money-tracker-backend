<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query
                        ->where('user_id', $this->user()->id)
                        ->where('is_archived', false)
                ),
            ],
            'wallet_id' => [
                'required',
                'integer',
                Rule::exists('wallets', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
            'type' => ['required', Rule::in(['incoming', 'outgoing'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string'],
            'invoice_image' => ['nullable', 'image', 'max:5120'],
            'invoice_images' => ['nullable', 'array', 'max:8'],
            'invoice_images.*' => ['image', 'max:5120'],
            'occurred_on' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'category_id' => [
                'description' => 'The category that owns the transaction.',
                'example' => 3,
            ],
            'wallet_id' => [
                'description' => 'The wallet that the transaction adds to or deducts from.',
                'example' => 1,
            ],
            'type' => [
                'description' => 'Whether the record adds money or spends money.',
                'example' => 'outgoing',
            ],
            'amount' => [
                'description' => 'Positive native amount before conversion.',
                'example' => 250000,
            ],
            'note' => [
                'description' => 'Optional free-text note for the record.',
                'example' => 'Supermarket and household items',
            ],
            'invoice_image' => [
                'description' => 'Optional invoice or receipt image. Deprecated; use invoice_images[] for multiple files.',
                'example' => null,
            ],
            'invoice_images' => [
                'description' => 'Optional invoice or receipt images.',
                'example' => null,
            ],
            'occurred_on' => [
                'description' => 'When the transaction happened.',
                'example' => '2026-05-10 18:45:00',
            ],
        ];
    }
}
