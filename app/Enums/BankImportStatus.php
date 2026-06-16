<?php

namespace App\Enums;

enum BankImportStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
