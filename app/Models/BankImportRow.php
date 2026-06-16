<?php

namespace App\Models;

use App\Enums\BankImportRowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankImportRow extends Model
{
    protected $fillable = [
        'bank_import_id',
        'company_id',
        'row_hash',
        'transaction_date',
        'description',
        'deposit_amount',
        'withdrawal_amount',
        'balance',
        'suggested_account_id',
        'journal_entry_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'deposit_amount' => 'integer',
            'withdrawal_amount' => 'integer',
            'balance' => 'integer',
            'status' => BankImportRowStatus::class,
        ];
    }

    public function bankImport(): BelongsTo
    {
        return $this->belongsTo(BankImport::class);
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
