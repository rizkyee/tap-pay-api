<?php

namespace App\Http\Requests\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->email ? strtolower(trim($this->email)) : null,
            'phone' => $this->phone ? preg_replace('/\s+/', '', trim($this->phone)) : null,
            'role' => $this->role ? strtolower(trim($this->role)) : User::ROLE_CUSTOMER,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'min:8', 'max:20', 'unique:users,phone'],
            'role' => [
                'required',
                Rule::in([
                    User::ROLE_CUSTOMER,
                    User::ROLE_MERCHANT,
                ]),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Role yang boleh registrasi hanya customer atau merchant.',
            'password.confirmed' => 'Konfirmasi password tidak sesuai.',
        ];
    }
}
