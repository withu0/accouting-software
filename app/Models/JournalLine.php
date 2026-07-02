<?php

namespace App\Models;

use App\Enums\ConsumptionTaxCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'consumption_tax_category',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'integer',
            'credit' => 'integer',
            'consumption_tax_category' => ConsumptionTaxCategory::class,
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
