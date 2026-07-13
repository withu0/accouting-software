<?php

namespace App\Models;

use App\Enums\ConsumptionTaxMethod;
use App\Enums\SimplifiedTaxIndustry;
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
        'consumption_tax_method',
        'simplified_tax_industry',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year_start_month' => 'integer',
            'consumption_tax_method' => ConsumptionTaxMethod::class,
            'simplified_tax_industry' => SimplifiedTaxIndustry::class,
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

    public function creditCardImports(): HasMany
    {
        return $this->hasMany(CreditCardImport::class);
    }
}
