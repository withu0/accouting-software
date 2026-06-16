<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAdvanceExpenseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
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

            $accountId = $this->input('account_id');
            if ($accountId !== null) {
                $expenseAccountIds = Account::expenseAccounts()->pluck('id')->all();
                if (! in_array((int) $accountId, $expenseAccountIds, true)) {
                    $validator->errors()->add('account_id', '経費科目のみ選択できます。');
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
            'entry_date.required' => '日付を入力してください。',
            'amount.required' => '金額を入力してください。',
            'amount.min' => '金額は1円以上で入力してください。',
            'description.required' => '摘要を入力してください。',
            'account_id.required' => '経費科目を選択してください。',
        ];
    }
}
