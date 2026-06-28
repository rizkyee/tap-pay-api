<?php

namespace App\Http\Requests\Api\Wallet;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest
{
    public function rules(): array
{
    return [
        'amount' => ['required', 'numeric', 'min:1000'],
    ];
}
}
