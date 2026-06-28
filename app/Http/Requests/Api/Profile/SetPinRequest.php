<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;

class SetPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'digits_between:4,8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'pin.confirmed' => 'Konfirmasi PIN tidak sesuai.',
            'pin.digits_between' => 'PIN harus berupa angka 4-8 digit.',
        ];
    }
}
