<?php

namespace App\Models;

use App\Enums\CreditCardImportRowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCardImportRow extends Model
{
    protected $fillable = [
        'credit_card_import_id',
        'company_id',
        'row_hash',
        'transaction_date',
        'description',
        'amount',
        'suggested_account_id',
        'journal_entry_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'integer',
            'status' => CreditCardImportRowStatus::class,
        ];
    }

    public function creditCardImport(): BelongsTo
    {
        return $this->belongsTo(CreditCardImport::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function suggestedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'suggested_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
