<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Account extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'display_order' => 'integer',
        ];
    }

    public function scopeOfType(Builder $query, AccountType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->ofType(AccountType::Expense)->orderBy('display_order');
    }

    public function scopeAsset(Builder $query): Builder
    {
        return $query->ofType(AccountType::Asset)->orderBy('display_order');
    }

    public function scopeLiability(Builder $query): Builder
    {
        return $query->ofType(AccountType::Liability)->orderBy('display_order');
    }

    public function scopeEquity(Builder $query): Builder
    {
        return $query->ofType(AccountType::Equity)->orderBy('display_order');
    }

    public function scopeRevenue(Builder $query): Builder
    {
        return $query->ofType(AccountType::Revenue)->orderBy('display_order');
    }

    public static function findByName(string $name): self
    {
        $account = self::where('name', $name)->first();

        if ($account === null) {
            throw new ModelNotFoundException("Account not found: {$name}");
        }

        return $account;
    }

    /**
     * @return Collection<int, self>
     */
    public static function expenseAccounts(): Collection
    {
        return self::expense()->get();
    }

    /**
     * @return Collection<int, self>
     */
    public static function allOrdered(): Collection
    {
        return self::orderBy('display_order')->get();
    }

    /**
     * @return array<string, list<array{id: int, name: string}>>
     */
    public static function groupedForSelect(): array
    {
        $typeLabels = [
            AccountType::Asset->value => '資産',
            AccountType::Liability->value => '負債',
            AccountType::Equity->value => '純資産',
            AccountType::Revenue->value => '収益',
            AccountType::Expense->value => '費用',
        ];

        $grouped = [];

        foreach (self::allOrdered() as $account) {
            $label = $typeLabels[$account->type->value] ?? $account->type->value;
            $grouped[$label][] = [
                'id' => $account->id,
                'name' => $account->name,
            ];
        }

        return $grouped;
    }
}
