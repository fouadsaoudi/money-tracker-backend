<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
                'max:100',
                Rule::unique('categories', 'name')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'The category name. Must be unique per user.',
                'example' => 'Freelance',
            ],
            'color' => [
                'description' => 'Optional color token or hex code for the category.',
                'example' => '#0f766e',
            ],
            'icon' => [
                'description' => 'Optional icon identifier used by the mobile client.',
                'example' => 'briefcase',
            ],
        ];
    }
}
