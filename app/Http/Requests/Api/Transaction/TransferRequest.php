<?php

namespace App\Http\Requests\Api\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receiver_id' => ['required', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:1000'],
            'fee' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}