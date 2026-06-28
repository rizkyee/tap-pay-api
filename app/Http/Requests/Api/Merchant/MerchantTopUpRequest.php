<?php

namespace App\Http\Requests\Api\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class MerchantTopUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->customer_phone) {
            $this->merge([
                'customer_phone' => preg_replace('/\s+/', '', trim($this->customer_phone)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_phone' => ['required', 'string', 'min:8', 'max:20'],
            'amount' => ['required', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
