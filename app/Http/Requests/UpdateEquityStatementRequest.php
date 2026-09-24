<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEquityStatementRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rows' => ['present', 'array'],
            'rows.*.label' => ['required', 'string', 'max:40'],
            'rows.*.capital' => ['required', 'integer'],
            'rows.*.capital_surplus' => ['required', 'integer'],
            'rows.*.retained_earnings' => ['required', 'integer'],
            'rows.*.treasury' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.*.label.required' => '変動事由を入力してください。',
            'rows.*.capital.integer' => '金額は整数で入力してください。',
            'rows.*.capital_surplus.integer' => '金額は整数で入力してください。',
            'rows.*.retained_earnings.integer' => '金額は整数で入力してください。',
            'rows.*.treasury.integer' => '金額は整数で入力してください。',
        ];
    }
}
