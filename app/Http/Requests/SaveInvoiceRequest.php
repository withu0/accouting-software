<?php

namespace App\Http\Requests;

use App\Enums\InvoiceTaxRate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInvoiceRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'issue_date' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.transaction_date' => ['required', 'date'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'lines.*.unit_price' => ['required', 'integer', 'min:0', 'max:99999999'],
            'lines.*.tax_rate' => ['required', Rule::enum(InvoiceTaxRate::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');

        if ($notes === '' || $notes === null) {
            $this->merge(['notes' => null]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'issue_date' => '発行日',
            'customer_name' => '宛先',
            'notes' => '備考',
            'lines' => '明細',
            'lines.*.transaction_date' => '取引日',
            'lines.*.description' => '取引内容',
            'lines.*.quantity' => '数量',
            'lines.*.unit_price' => '単価',
            'lines.*.tax_rate' => '税率',
        ];
    }
}
