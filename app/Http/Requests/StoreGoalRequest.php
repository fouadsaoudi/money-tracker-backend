<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoalRequest extends FormRequest
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
                'required',
                'string',
                'max:120',
                Rule::unique('goals', 'name')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
            'currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where('is_active', true),
            ],
            'target_amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string'],
        ];
    }
}
