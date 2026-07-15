<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\BankImportRow;
use App\Http\Requests\Concerns\ValidatesConsumptionTax;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConfirmBankImportRequest extends FormRequest
{
    use ValidatesConsumptionTax;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $taxRules = $this->consumptionTaxRules();

        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.row_id' => ['required', 'integer'],
            'rows.*.account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'rows.*.consumption_tax_category' => $taxRules['consumption_tax_category'],
            'rows.*.has_qualified_invoice' => $taxRules['has_qualified_invoice'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $company = $this->user()?->ensureCompany();
            $bankImport = $this->route('bankImport');

            $expenseAccountIds = Account::expenseAccounts()->pluck('id')->all();

            foreach ($this->input('rows', []) as $index => $rowData) {
                $rowId = $rowData['row_id'] ?? null;
                if ($rowId === null) {
                    continue;
                }

                $row = BankImportRow::where('bank_import_id', $bankImport->id)
                    ->where('company_id', $company->id)
                    ->where('id', $rowId)
                    ->first();

                if ($row === null) {
                    $validator->errors()->add("rows.{$index}.row_id", '無効な取引行です。');

                    continue;
                }

                if ($row->withdrawal_amount > 0 && empty($rowData['account_id'])) {
                    $validator->errors()->add("rows.{$index}.account_id", '出金の場合は経費科目を選択してください。');
                }

                if (! empty($rowData['account_id']) && ! in_array((int) $rowData['account_id'], $expenseAccountIds, true)) {
                    $validator->errors()->add("rows.{$index}.account_id", '経費科目のみ選択できます。');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => '記帳する取引を選択してください。',
            'rows.min' => '記帳する取引を選択してください。',
            'rows.*.consumption_tax_category.required' => '税区分を選択してください。',
        ];
    }
}
