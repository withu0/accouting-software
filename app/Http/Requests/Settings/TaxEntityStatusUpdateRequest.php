<?php

namespace App\Http\Requests\Settings;

use App\Enums\TaxEntityStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxEntityStatusUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tax_entity_status' => ['required', Rule::enum(TaxEntityStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tax_entity_status.required' => '消費税の事業者区分を選択してください。',
        ];
    }
};
