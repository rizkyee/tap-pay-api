<?php

namespace App\Http\Requests\Api\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class MerchantTopUpByCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_code' => ['required', 'string', 'min:10', 'max:30'],
        ];
    }
}
