<?php

namespace App\Enums;

enum BankImportRowStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Skipped = 'skipped';
}
