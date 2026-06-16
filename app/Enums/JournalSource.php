<?php

namespace App\Enums;

enum JournalSource: string
{
    case BankCsv = 'bank_csv';
    case AdvanceExpense = 'advance_expense';
    case Transfer = 'transfer';
    case Manual = 'manual';
}
