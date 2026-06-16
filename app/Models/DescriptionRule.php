<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DescriptionRule extends Model
{
    protected $fillable = [
        'company_id',
        'keyword',
        'account_id',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
