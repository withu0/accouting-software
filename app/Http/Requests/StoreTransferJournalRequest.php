<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTransferJournalRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'debit_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'debit_amount' => ['required', 'integer', 'min:1'],
            'credit_account_id' => ['required', 'integer', 'exists:accounts,id', 'different:debit_account_id'],
            'credit_amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $company = $this->user()?->company;
            if ($company === null) {
                return;
            }

            $fiscalYear = $company->activeFiscalYear();
            if ($fiscalYear === null) {
                $validator->errors()->add('entry_date', '会計期間が設定されていません。');

                return;
            }

            $entryDate = $this->input('entry_date');
            if ($entryDate !== null) {
                if ($entryDate < $fiscalYear->start_date->format('Y-m-d') || $entryDate > $fiscalYear->end_date->format('Y-m-d')) {
                    $validator->errors()->add('entry_date', '日付は会計期間内である必要があります。');
                }
            }

            $debitAmount = (int) $this->input('debit_amount', 0);
            $creditAmount = (int) $this->input('credit_amount', 0);

            if ($debitAmount > 0 && $creditAmount > 0 && $debitAmount !== $creditAmount) {
                $validator->errors()->add('credit_amount', '借方金額と貸方金額は一致している必要があります。');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entry_date.required' => '日付を入力してください。',
            'debit_account_id.required' => '借方科目を選択してください。',
            'debit_amount.required' => '借方金額を入力してください。',
            'debit_amount.min' => '借方金額は1円以上で入力してください。',
            'credit_account_id.required' => '貸方科目を選択してください。',
            'credit_account_id.different' => '借方科目と貸方科目は異なる必要があります。',
            'credit_amount.required' => '貸方金額を入力してください。',
            'credit_amount.min' => '貸方金額は1円以上で入力してください。',
            'description.required' => '摘要を入力してください。',
        ];
    }
}
