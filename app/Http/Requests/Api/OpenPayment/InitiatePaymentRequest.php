<?php

namespace App\Http\Requests\Api\OpenPayment;

use Illuminate\Foundation\Http\FormRequest;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receiver_nfc_token' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
