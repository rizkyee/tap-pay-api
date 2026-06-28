<?php

namespace App\Http\Requests\Api\Transfer;

use Illuminate\Foundation\Http\FormRequest;

class RejectTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
