<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'representative_name',
        'address',
        'fiscal_year_start_month',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year_start_month' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class);
    }

    public function activeFiscalYear(): ?FiscalYear
    {
        return $this->fiscalYears()->where('is_active', true)->first();
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function descriptionRules(): HasMany
    {
        return $this->hasMany(DescriptionRule::class);
    }

    public function bankImports(): HasMany
    {
        return $this->hasMany(BankImport::class);
    }
}
