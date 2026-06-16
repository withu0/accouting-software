<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\DescriptionRule;

class DescriptionRuleMatcher
{
    public function suggestAccount(Company $company, string $description): ?Account
    {
        $rules = $company->descriptionRules()
            ->with('account')
            ->get()
            ->sortByDesc(fn (DescriptionRule $rule) => mb_strlen($rule->keyword));

        foreach ($rules as $rule) {
            if (str_contains($description, $rule->keyword)) {
                return $rule->account;
            }
        }

        return null;
    }

    public function learnFromConfirmation(Company $company, string $description, int $accountId): void
    {
        $matchingRule = $this->findMatchingRule($company, $description);

        if ($matchingRule !== null) {
            $matchingRule->update(['account_id' => $accountId]);

            return;
        }

        $keyword = $this->extractKeyword($description);

        DescriptionRule::updateOrCreate(
            [
                'company_id' => $company->id,
                'keyword' => $keyword,
            ],
            [
                'account_id' => $accountId,
                'priority' => mb_strlen($keyword),
            ],
        );
    }

    private function findMatchingRule(Company $company, string $description): ?DescriptionRule
    {
        $rules = $company->descriptionRules()
            ->get()
            ->sortByDesc(fn (DescriptionRule $rule) => mb_strlen($rule->keyword));

        foreach ($rules as $rule) {
            if (str_contains($description, $rule->keyword)) {
                return $rule;
            }
        }

        return null;
    }

    private function extractKeyword(string $description): string
    {
        $tokens = preg_split('/[\s,、．.（）()]+/u', $description, -1, PREG_SPLIT_NO_EMPTY);

        if ($tokens === false || $tokens === []) {
            return mb_substr($description, 0, 20);
        }

        $longest = '';
        foreach ($tokens as $token) {
            $alphanumeric = preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fff}]/u', '', $token) ?? '';
            if (mb_strlen($alphanumeric) >= 2 && mb_strlen($alphanumeric) > mb_strlen($longest)) {
                $longest = $alphanumeric;
            }
        }

        if ($longest !== '') {
            return $longest;
        }

        foreach ($tokens as $token) {
            if (mb_strlen($token) >= 2) {
                return $token;
            }
        }

        return mb_substr($description, 0, 20);
    }
}
