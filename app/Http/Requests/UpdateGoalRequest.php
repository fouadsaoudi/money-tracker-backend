<?php

namespace App\Http\Requests;

use App\Models\Goal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGoalRequest extends FormRequest
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
        /** @var Goal|null $goal */
        $goal = $this->route('goal');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('goals', 'name')
                    ->ignore($goal?->id)
                    ->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'target_amount' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'note' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
