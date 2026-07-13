<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\CreditCardImportRow;
use App\Http\Requests\Concerns\ValidatesConsumptionTax;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConfirmCreditCardImportRequest extends FormRequest
{
    use ValidatesConsumptionTax;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.row_id' => ['required', 'integer'],
            'rows.*.account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'rows.*.consumption_tax_category' => ['nullable', 'string'],
            'rows.*.has_qualified_invoice' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $company = $this->user()?->ensureCompany();
            $creditCardImport = $this->route('import');

            $expenseAccountIds = Account::expenseAccounts()->pluck('id')->all();
            $missingAccountCount = 0;

            foreach ($this->input('rows', []) as $index => $rowData) {
                $rowId = $rowData['row_id'] ?? null;
                if ($rowId === null) {
                    continue;
                }

                $row = CreditCardImportRow::where('credit_card_import_id', $creditCardImport->id)
                    ->where('company_id', $company->id)
                    ->where('id', $rowId)
                    ->first();

                if ($row === null) {
                    $validator->errors()->add('rows', '無効な取引行が含まれています。ページを更新してから再度お試しください。');

                    continue;
                }

                if (empty($rowData['account_id'])) {
                    $missingAccountCount++;

                    continue;
                }

                if (! in_array((int) $rowData['account_id'], $expenseAccountIds, true)) {
                    $validator->errors()->add('rows', '経費科目のみ選択できます。');
                }
            }

            if ($missingAccountCount > 0) {
                $validator->errors()->add('rows', '経費科目が未選択の取引があります。すべての取引に経費科目を選択してください。');
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
            'rows.*.row_id.required' => '記帳する取引を選択してください。',
            'rows.*.account_id.exists' => '選択した経費科目が見つかりません。',
        ];
    }
}
