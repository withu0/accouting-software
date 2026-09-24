<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquityStatementLine extends Model
{
    protected $fillable = [
        'equity_statement_override_id',
        'label',
        'capital',
        'capital_surplus',
        'retained_earnings',
        'treasury',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'capital' => 'integer',
            'capital_surplus' => 'integer',
            'retained_earnings' => 'integer',
            'treasury' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function override(): BelongsTo
    {
        return $this->belongsTo(EquityStatementOverride::class, 'equity_statement_override_id');
    }
}
