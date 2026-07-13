<?php

namespace App\Enums;

enum CreditCardImportStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
