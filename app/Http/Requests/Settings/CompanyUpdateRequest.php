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
            'fiscal_year_start_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'consumption_tax_method' => ['nullable', Rule::enum(ConsumptionTaxMethod::class)],
            'simplified_tax_industry' => [
                'nullable',
                Rule::enum(SimplifiedTaxIndustry::class),
                Rule::requiredIf(fn () => $this->input('consumption_tax_method') === ConsumptionTaxMethod::Simplified->value),
            ],
        ];
    }
}
