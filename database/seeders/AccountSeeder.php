<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\ConsumptionTaxCategory;
use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $outOfScope = ConsumptionTaxCategory::OutOfScope;
        $taxableSales10 = ConsumptionTaxCategory::TaxableSales10;
        $taxablePurchase10 = ConsumptionTaxCategory::TaxablePurchase10;
        $nonTaxable = ConsumptionTaxCategory::NonTaxable;

        $accounts = [
            // Assets
            ['code' => '1100', 'name' => '現金', 'type' => AccountType::Asset, 'display_order' => 1, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1101', 'name' => '普通預金', 'type' => AccountType::Asset, 'display_order' => 2, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1113', 'name' => '当座預金', 'type' => AccountType::Asset, 'display_order' => 3, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1102', 'name' => '売掛金', 'type' => AccountType::Asset, 'display_order' => 4, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1114', 'name' => '未収入金', 'type' => AccountType::Asset, 'display_order' => 5, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1103', 'name' => '前払費用', 'type' => AccountType::Asset, 'display_order' => 6, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1115', 'name' => '仮払金', 'type' => AccountType::Asset, 'display_order' => 7, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1116', 'name' => '立替金', 'type' => AccountType::Asset, 'display_order' => 8, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1117', 'name' => '棚卸資産', 'type' => AccountType::Asset, 'display_order' => 9, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1104', 'name' => '仮払税金', 'type' => AccountType::Asset, 'display_order' => 10, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1105', 'name' => '建物', 'type' => AccountType::Asset, 'display_order' => 11, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1106', 'name' => '建物附属設備', 'type' => AccountType::Asset, 'display_order' => 12, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1107', 'name' => '工具器具備品', 'type' => AccountType::Asset, 'display_order' => 13, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1108', 'name' => '車両運搬具', 'type' => AccountType::Asset, 'display_order' => 14, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1118', 'name' => 'ソフトウェア', 'type' => AccountType::Asset, 'display_order' => 15, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1119', 'name' => '減価償却累計額', 'type' => AccountType::Asset, 'display_order' => 16, 'is_contra' => true, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1109', 'name' => '敷金・保証金', 'type' => AccountType::Asset, 'display_order' => 17, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1120', 'name' => '貸付金', 'type' => AccountType::Asset, 'display_order' => 18, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1110', 'name' => '繰延資産', 'type' => AccountType::Asset, 'display_order' => 19, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1111', 'name' => '長期前払費用', 'type' => AccountType::Asset, 'display_order' => 20, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '1112', 'name' => '仮払消費税', 'type' => AccountType::Asset, 'display_order' => 21, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],

            // Liabilities
            ['code' => '2101', 'name' => '買掛金', 'type' => AccountType::Liability, 'display_order' => 22, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '2102', 'name' => '未払金', 'type' => AccountType::Liability, 'display_order' => 23, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '2103', 'name' => '未払費用', 'type' => AccountType::Liability, 'display_order' => 24, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '2106', 'name' => '未払法人税等', 'type' => AccountType::Liability, 'display_order' => 25, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '2108', 'name' => '未払消費税等', 'type' => AccountType::Liability, 'display_order' => 26, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '2104', 'name' => '預り金', 'type' => AccountType::Liability, 'display_order' => 27, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '2109', 'name' => '借入金', 'type' => AccountType::Liability, 'display_order' => 28, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '2110', 'name' => '前受金', 'type' => AccountType::Liability, 'display_order' => 29, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '2105', 'name' => '役員借入金', 'type' => AccountType::Liability, 'display_order' => 30, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '2107', 'name' => '仮受消費税', 'type' => AccountType::Liability, 'display_order' => 31, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],

            // Equity
            ['code' => '3101', 'name' => '資本金', 'type' => AccountType::Equity, 'display_order' => 32, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '3102', 'name' => '資本準備金', 'type' => AccountType::Equity, 'display_order' => 33, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '3103', 'name' => '繰越利益剰余金', 'type' => AccountType::Equity, 'display_order' => 34, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '3104', 'name' => '自己株式', 'type' => AccountType::Equity, 'display_order' => 35, 'is_contra' => true, 'default_consumption_tax_category' => $outOfScope],

            // Revenue
            ['code' => '4101', 'name' => '売上高', 'type' => AccountType::Revenue, 'display_order' => 36, 'is_contra' => false, 'default_consumption_tax_category' => $taxableSales10],

            // Expenses
            ['code' => '5101', 'name' => '会議費', 'type' => AccountType::Expense, 'display_order' => 37, 'is_contra' => false, 'default_consumption_tax_category' => $taxablePurchase10],
            ['code' => '5102', 'name' => '接待交際費', 'type' => AccountType::Expense, 'display_order' => 38, 'is_contra' => false, 'default_consumption_tax_category' => $taxablePurchase10],
            ['code' => '5103', 'name' => '旅費交通費', 'type' => AccountType::Expense, 'display_order' => 39, 'is_contra' => false, 'default_consumption_tax_category' => $taxablePurchase10],
            ['code' => '5104', 'name' => '水道光熱費', 'type' => AccountType::Expense, 'display_order' => 40, 'is_contra' => false, 'default_consumption_tax_category' => $taxablePurchase10],
            ['code' => '5105', 'name' => '租税公課', 'type' => AccountType::Expense, 'display_order' => 41, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '5106', 'name' => '法定福利費', 'type' => AccountType::Expense, 'display_order' => 42, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '5107', 'name' => '福利厚生費', 'type' => AccountType::Expense, 'display_order' => 43, 'is_contra' => false, 'default_consumption_tax_category' => $taxablePurchase10],
            ['code' => '5108', 'name' => '通信費', 'type' => AccountType::Expense, 'display_order' => 44, 'is_contra' => false, 'default_consumption_tax_category' => $taxablePurchase10],
            ['code' => '5109', 'name' => '支払利子', 'type' => AccountType::Expense, 'display_order' => 45, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '5110', 'name' => '地代家賃', 'type' => AccountType::Expense, 'display_order' => 46, 'is_contra' => false, 'default_consumption_tax_category' => $nonTaxable],
            ['code' => '5111', 'name' => '減価償却費', 'type' => AccountType::Expense, 'display_order' => 47, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
            ['code' => '5112', 'name' => '保険料', 'type' => AccountType::Expense, 'display_order' => 48, 'is_contra' => false, 'default_consumption_tax_category' => $nonTaxable],
            ['code' => '5113', 'name' => '支払手数料', 'type' => AccountType::Expense, 'display_order' => 49, 'is_contra' => false, 'default_consumption_tax_category' => $taxablePurchase10],
            ['code' => '5114', 'name' => '消耗品費', 'type' => AccountType::Expense, 'display_order' => 50, 'is_contra' => false, 'default_consumption_tax_category' => $taxablePurchase10],
            ['code' => '5115', 'name' => '諸会費', 'type' => AccountType::Expense, 'display_order' => 51, 'is_contra' => false, 'default_consumption_tax_category' => $taxablePurchase10],
            ['code' => '5116', 'name' => '広告宣伝費', 'type' => AccountType::Expense, 'display_order' => 52, 'is_contra' => false, 'default_consumption_tax_category' => $taxablePurchase10],
            ['code' => '5117', 'name' => '外注費', 'type' => AccountType::Expense, 'display_order' => 53, 'is_contra' => false, 'default_consumption_tax_category' => $taxablePurchase10],
            ['code' => '5118', 'name' => '雑費', 'type' => AccountType::Expense, 'display_order' => 54, 'is_contra' => false, 'default_consumption_tax_category' => $taxablePurchase10],
            ['code' => '5119', 'name' => '法人税、住民税及び事業税', 'type' => AccountType::Expense, 'display_order' => 55, 'is_contra' => false, 'default_consumption_tax_category' => $outOfScope],
        ];

        foreach ($accounts as $account) {
            Account::updateOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'display_order' => $account['display_order'],
                    'is_contra' => $account['is_contra'],
                    'default_consumption_tax_category' => $account['default_consumption_tax_category']->value,
                ],
            );
        }
    }
}
