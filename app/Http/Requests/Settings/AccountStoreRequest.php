<?php

namespace App\Http\Requests\Settings;

use App\Enums\AccountType;
use App\Enums\ConsumptionTaxCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountStoreRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^\d+$/', 'unique:accounts,code'],
            'name' => ['required', 'string', 'max:255', 'unique:accounts,name'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'display_order' => ['nullable', 'integer', 'min:1'],
            'default_consumption_tax_category' => ['nullable', Rule::enum(ConsumptionTaxCategory::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => '科目コードは数字のみで入力してください。',
            'code.unique' => 'この科目コードは既に使用されています。',
            'name.unique' => 'この科目名は既に使用されています。',
        ];
    }
}
