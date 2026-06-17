<?php

namespace App\Http\Requests\Settings;

use App\Enums\AccountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $account = $this->route('account');

        return [
            'code' => ['required', 'string', 'regex:/^\d+$/', Rule::unique('accounts', 'code')->ignore($account->id)],
            'name' => ['required', 'string', 'max:255', Rule::unique('accounts', 'name')->ignore($account->id)],
            'type' => ['required', Rule::enum(AccountType::class)],
            'display_order' => ['required', 'integer', 'min:1'],
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
