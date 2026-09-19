<?php

namespace App\Http\Requests\Api\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class MerchantTopupCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1000'],
        ];
    }
}
