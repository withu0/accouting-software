<?php

namespace App\Enums;

enum CreditCardImportRowStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Skipped = 'skipped';
}
