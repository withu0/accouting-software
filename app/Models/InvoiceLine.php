<?php

namespace App\Models;

use App\Enums\InvoiceTaxRate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id',
        'line_no',
        'transaction_date',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'transaction_date' => 'date',
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'tax_rate' => InvoiceTaxRate::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
