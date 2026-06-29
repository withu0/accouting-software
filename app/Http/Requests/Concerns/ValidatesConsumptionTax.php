<?php

namespace App\Http\Requests\Concerns;

use App\Enums\ConsumptionTaxCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ValidatesConsumptionTax
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function consumptionTaxRules(bool $requireCategory = true): array
    {
        $categoryRule = $requireCategory ? ['required'] : ['nullable'];

        return [
            'consumption_tax_category' => [
                ...$categoryRule,
                Rule::enum(ConsumptionTaxCategory::class),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $category = ConsumptionTaxCategory::from((string) $value);
                    if (! $category->isUserSelectable()) {
                        $fail('選択できない税区分です。');
                    }
                },
            ],
            'has_qualified_invoice' => ['nullable', 'boolean'],
        ];
    }
}
