<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        /** @var Category|null $category */
        $category = $this->route('category');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->ignore($category?->id)
                    ->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_archived' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Updated category name. Must remain unique per user.',
                'example' => 'Household Bills',
            ],
            'color' => [
                'description' => 'Updated category color.',
                'example' => '#dc2626',
            ],
            'icon' => [
                'description' => 'Updated icon identifier.',
                'example' => 'receipt',
            ],
            'is_archived' => [
                'description' => 'Whether the category should be archived.',
                'example' => false,
            ],
        ];
    }
}
