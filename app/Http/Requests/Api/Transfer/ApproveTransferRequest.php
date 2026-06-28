<?php

namespace App\Http\Requests\Api\Transfer;

use Illuminate\Foundation\Http\FormRequest;

class ApproveTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'min:4', 'max:8'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
