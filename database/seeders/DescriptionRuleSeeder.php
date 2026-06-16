<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\DescriptionRule;
use Illuminate\Database\Seeder;

class DescriptionRuleSeeder extends Seeder
{
    /**
     * @var array<int, array{keyword: string, account: string}>
     */
    private const DEFAULT_RULES = [
        ['keyword' => 'Amazon', 'account' => '消耗品費'],
        ['keyword' => 'JR', 'account' => '旅費交通費'],
        ['keyword' => 'NTT', 'account' => '通信費'],
        ['keyword' => 'Google', 'account' => '広告宣伝費'],
        ['keyword' => '税務署', 'account' => '租税公課'],
        ['keyword' => '日本年金機構', 'account' => '法定福利費'],
        ['keyword' => '会議費', 'account' => '会議費'],
    ];

    public static function seedForCompany(Company $company): void
    {
        foreach (self::DEFAULT_RULES as $rule) {
            $account = Account::where('name', $rule['account'])->first();
            if ($account === null) {
                continue;
            }

            DescriptionRule::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'keyword' => $rule['keyword'],
                ],
                [
                    'account_id' => $account->id,
                    'priority' => mb_strlen($rule['keyword']),
                ],
            );
        }
    }

    public function run(): void
    {
        Company::all()->each(fn (Company $company) => self::seedForCompany($company));
    }
}
