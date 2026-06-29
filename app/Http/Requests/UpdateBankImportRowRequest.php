<?php

namespace App\Http\Requests;

use App\Enums\BankImportRowStatus;
use App\Http\Requests\Concerns\ValidatesConsumptionTax;
use App\Models\BankImportRow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBankImportRowRequest extends FormRequest
{
    use ValidatesConsumptionTax;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge([
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
        ], $this->consumptionTaxRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $company = $this->user()?->ensureCompany();
            if ($company === null) {
                return;
            }

            $fiscalYear = $company->activeFiscalYear();
            if ($fiscalYear === null) {
                $validator->errors()->add('transaction_date', '会計期間が設定されていません。');

                return;
            }

            $entryDate = $this->input('transaction_date');
            if ($entryDate !== null) {
                if ($entryDate < $fiscalYear->start_date->format('Y-m-d') || $entryDate > $fiscalYear->end_date->format('Y-m-d')) {
                    $validator->errors()->add('transaction_date', '日付は会計期間内である必要があります。');
                }
            }

            /** @var BankImportRow|null $row */
            $row = $this->route('row');
            if ($row === null || $row->company_id !== $company->id) {
                return;
            }

            if ($row->status === BankImportRowStatus::Skipped) {
                $validator->errors()->add('amount', 'スキップ済みの取引は編集できません。');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transaction_date.required' => '取引日を入力してください。',
            'description.required' => '摘要を入力してください。',
            'amount.required' => '金額を入力してください。',
            'amount.min' => '金額は1円以上で入力してください。',
            'account_id.required' => '勘定科目を選択してください。',
        ];
    }
}
