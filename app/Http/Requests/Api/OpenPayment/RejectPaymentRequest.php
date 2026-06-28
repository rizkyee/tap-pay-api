<?php

namespace App\Http\Requests\Api\OpenPayment;

use Illuminate\Foundation\Http\FormRequest;

class RejectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
