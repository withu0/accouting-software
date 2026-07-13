<?php

namespace App\Models;

use App\Enums\CreditCardCsvFormat;
use App\Enums\CreditCardImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCardImport extends Model
{
    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'original_filename',
        'detected_format',
        'card_name',
        'payment_date',
        'billing_amount',
        'status',
        'row_count',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CreditCardImportStatus::class,
            'detected_format' => CreditCardCsvFormat::class,
            'payment_date' => 'date',
            'billing_amount' => 'integer',
            'row_count' => 'integer',
            'imported_at' => 'datetime',
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

    public function rows(): HasMany
    {
        return $this->hasMany(CreditCardImportRow::class);
    }
}
