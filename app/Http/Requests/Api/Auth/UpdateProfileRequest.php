<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->name ? trim($this->name) : null,
            'phone' => $this->phone ? preg_replace('/\s+/', '', trim($this->phone)) : null,
        ]);
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'min:8',
                'max:20',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
        ];
    }
}
