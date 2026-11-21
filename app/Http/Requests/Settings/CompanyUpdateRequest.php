<?php

namespace App\Http\Requests\Settings;

use App\Enums\ConsumptionTaxMethod;
use App\Enums\SimplifiedTaxIndustry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'representative_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'invoice_registration_number' => ['nullable', 'string', 'regex:/^T\d{13}$/'],
            'fiscal_year_start_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'consumption_tax_method' => ['nullable', Rule::enum(ConsumptionTaxMethod::class)],
            'simplified_tax_industry' => [
                'nullable',
                Rule::enum(SimplifiedTaxIndustry::class),
                Rule::requiredIf(fn () => $this->input('consumption_tax_method') === ConsumptionTaxMethod::Simplified->value),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('invoice_registration_number')) {
            return;
        }

        $value = strtoupper(trim((string) $this->input('invoice_registration_number')));

        $this->merge([
            'invoice_registration_number' => $value === '' ? null : $value,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_registration_number.regex' => '登録番号はTと13桁の数字で入力してください。',
        ];
    }
}
