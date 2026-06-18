<?php

namespace App\Models;

use App\Enums\BankImportStatus;
use App\Enums\BankCsvFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankImport extends Model
{
    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'original_filename',
        'detected_format',
        'status',
        'row_count',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BankImportStatus::class,
            'detected_format' => BankCsvFormat::class,
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
        return $this->hasMany(BankImportRow::class);
    }
}
