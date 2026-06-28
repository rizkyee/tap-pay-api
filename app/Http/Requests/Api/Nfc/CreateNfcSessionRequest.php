<?php

namespace App\Http\Requests\Api\Nfc;

use App\Models\NfcSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateNfcSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['sometimes', Rule::in([NfcSession::MODE_RECEIVE])],
        ];
    }
}
