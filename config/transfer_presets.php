<?php

return [
    [
        'id' => 'accounts_receivable',
        'label' => '売掛金計上',
        'debit_account' => '売掛金',
        'credit_account' => '売上高',
        'description' => '売掛金計上',
    ],
    [
        'id' => 'accounts_payable',
        'label' => '買掛金計上',
        'debit_account' => '消耗品費',
        'credit_account' => '買掛金',
        'description' => '買掛金計上',
    ],
    [
        'id' => 'accrued_payable',
        'label' => '未払金計上',
        'debit_account' => '支払手数料',
        'credit_account' => '未払金',
        'description' => '未払金計上',
    ],
    [
        'id' => 'prepaid_expense',
        'label' => '前払費用',
        'debit_account' => '前払費用',
        'credit_account' => '預金',
        'description' => '前払費用計上',
    ],
    [
        'id' => 'depreciation',
        'label' => '減価償却',
        'debit_account' => '減価償却費',
        'credit_account' => '建物附属設備',
        'description' => '減価償却',
    ],
    [
        'id' => 'corporate_tax',
        'label' => '法人税計上',
        'debit_account' => '法人税、住民税及び事業税',
        'credit_account' => '未払法人税等',
        'description' => '法人税計上',
    ],
    [
        'id' => 'officer_loan_repayment',
        'label' => '役員借入金返済',
        'debit_account' => '役員借入金',
        'credit_account' => '預金',
        'description' => '役員借入金返済',
    ],
];
