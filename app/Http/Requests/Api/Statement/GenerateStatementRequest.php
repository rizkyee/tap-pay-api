<?php

namespace App\Http\Requests\Api\Statement;

use App\Models\Statement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => [
                'sometimes',
                Rule::in([
                    Statement::TYPE_ALL,
                    Statement::TYPE_TRANSFER,
                    Statement::TYPE_TOPUP,
                    Statement::TYPE_INCOMING,
                    Statement::TYPE_OUTGOING,
                ]),
            ],
        ];
    }
}
