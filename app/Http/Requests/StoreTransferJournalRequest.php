<?php

namespace App\Http\Requests;

use App\Enums\ConsumptionTaxCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransferJournalRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $transferTaxValues = array_map(
            fn (ConsumptionTaxCategory $case) => $case->value,
            [ConsumptionTaxCategory::OutOfScope, ConsumptionTaxCategory::NonTaxable],
        );

        return [
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.debit' => ['required', 'integer', 'min:0'],
            'lines.*.credit' => ['required', 'integer', 'min:0'],
            'lines.*.consumption_tax_category' => [
                'required',
                Rule::in($transferTaxValues),
            ],
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

            $lines = $this->input('lines', []);
            if (! is_array($lines)) {
                return;
            }

            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($lines as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $debit = (int) ($line['debit'] ?? 0);
                $credit = (int) ($line['credit'] ?? 0);

                if (($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
                    $validator->errors()->add("lines.{$index}.debit", '各行は借方または貸方のいずれか一方に金額を入力してください。');
                }

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            if ($totalDebit > 0 && $totalCredit > 0 && $totalDebit !== $totalCredit) {
                $validator->errors()->add('lines', '借方合計と貸方合計が一致していません。');
            }

            if ($totalDebit === 0 && $totalCredit === 0) {
                $validator->errors()->add('lines', '金額を入力してください。');
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
            'description.required' => '摘要を入力してください。',
            'lines.required' => '仕訳行を入力してください。',
            'lines.min' => '仕訳行は2行以上必要です。',
            'lines.*.account_id.required' => '勘定科目を選択してください。',
            'lines.*.consumption_tax_category.required' => '税区分を選択してください。',
        ];
    }
}
