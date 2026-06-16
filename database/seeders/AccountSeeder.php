<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Assets (11)
            ['code' => '1101', 'name' => '預金', 'type' => AccountType::Asset, 'display_order' => 1],
            ['code' => '1102', 'name' => '売掛金', 'type' => AccountType::Asset, 'display_order' => 2],
            ['code' => '1103', 'name' => '前払費用', 'type' => AccountType::Asset, 'display_order' => 3],
            ['code' => '1104', 'name' => '仮払税金', 'type' => AccountType::Asset, 'display_order' => 4],
            ['code' => '1105', 'name' => '建物', 'type' => AccountType::Asset, 'display_order' => 5],
            ['code' => '1106', 'name' => '建物附属設備', 'type' => AccountType::Asset, 'display_order' => 6],
            ['code' => '1107', 'name' => '工具器具備品', 'type' => AccountType::Asset, 'display_order' => 7],
            ['code' => '1108', 'name' => '車両運搬具', 'type' => AccountType::Asset, 'display_order' => 8],
            ['code' => '1109', 'name' => '敷金', 'type' => AccountType::Asset, 'display_order' => 9],
            ['code' => '1110', 'name' => '繰延資産', 'type' => AccountType::Asset, 'display_order' => 10],
            ['code' => '1111', 'name' => '長期前払費用', 'type' => AccountType::Asset, 'display_order' => 11],

            // Liabilities (6)
            ['code' => '2101', 'name' => '買掛金', 'type' => AccountType::Liability, 'display_order' => 12],
            ['code' => '2102', 'name' => '未払金', 'type' => AccountType::Liability, 'display_order' => 13],
            ['code' => '2103', 'name' => '未払費用', 'type' => AccountType::Liability, 'display_order' => 14],
            ['code' => '2104', 'name' => '預かり金', 'type' => AccountType::Liability, 'display_order' => 15],
            ['code' => '2105', 'name' => '役員借入金', 'type' => AccountType::Liability, 'display_order' => 16],
            ['code' => '2106', 'name' => '未払法人税等', 'type' => AccountType::Liability, 'display_order' => 17],

            // Equity (1)
            ['code' => '3101', 'name' => '元入金', 'type' => AccountType::Equity, 'display_order' => 18],

            // Revenue (1)
            ['code' => '4101', 'name' => '売上高', 'type' => AccountType::Revenue, 'display_order' => 19],

            // Expenses (19)
            ['code' => '5101', 'name' => '会議費', 'type' => AccountType::Expense, 'display_order' => 20],
            ['code' => '5102', 'name' => '接待交際費', 'type' => AccountType::Expense, 'display_order' => 21],
            ['code' => '5103', 'name' => '旅費交通費', 'type' => AccountType::Expense, 'display_order' => 22],
            ['code' => '5104', 'name' => '水道光熱費', 'type' => AccountType::Expense, 'display_order' => 23],
            ['code' => '5105', 'name' => '租税公課', 'type' => AccountType::Expense, 'display_order' => 24],
            ['code' => '5106', 'name' => '法定福利費', 'type' => AccountType::Expense, 'display_order' => 25],
            ['code' => '5107', 'name' => '福利厚生費', 'type' => AccountType::Expense, 'display_order' => 26],
            ['code' => '5108', 'name' => '通信費', 'type' => AccountType::Expense, 'display_order' => 27],
            ['code' => '5109', 'name' => '支払利子', 'type' => AccountType::Expense, 'display_order' => 28],
            ['code' => '5110', 'name' => '地代家賃', 'type' => AccountType::Expense, 'display_order' => 29],
            ['code' => '5111', 'name' => '減価償却費', 'type' => AccountType::Expense, 'display_order' => 30],
            ['code' => '5112', 'name' => '保険料', 'type' => AccountType::Expense, 'display_order' => 31],
            ['code' => '5113', 'name' => '支払手数料', 'type' => AccountType::Expense, 'display_order' => 32],
            ['code' => '5114', 'name' => '消耗品費', 'type' => AccountType::Expense, 'display_order' => 33],
            ['code' => '5115', 'name' => '諸会費', 'type' => AccountType::Expense, 'display_order' => 34],
            ['code' => '5116', 'name' => '広告宣伝費', 'type' => AccountType::Expense, 'display_order' => 35],
            ['code' => '5117', 'name' => '外注費', 'type' => AccountType::Expense, 'display_order' => 36],
            ['code' => '5118', 'name' => '雑費', 'type' => AccountType::Expense, 'display_order' => 37],
            ['code' => '5119', 'name' => '法人税、住民税及び事業税', 'type' => AccountType::Expense, 'display_order' => 38],
        ];

        foreach ($accounts as $account) {
            Account::updateOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'display_order' => $account['display_order'],
                ],
            );
        }
    }
}
