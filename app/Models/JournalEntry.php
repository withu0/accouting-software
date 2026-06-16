<?php

namespace App\Models;

use App\Enums\JournalSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JournalEntry extends Model
{
    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'entry_date',
        'description',
        'source',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'source' => JournalSource::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function bankImportRow(): HasOne
    {
        return $this->hasOne(BankImportRow::class);
    }
}
