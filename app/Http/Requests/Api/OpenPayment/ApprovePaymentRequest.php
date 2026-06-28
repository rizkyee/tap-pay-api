<?php

namespace App\Http\Requests\Api\OpenPayment;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'pin' => ['required', 'string', 'min:4', 'max:8'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
