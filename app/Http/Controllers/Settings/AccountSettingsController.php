<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AccountStoreRequest;
use App\Http\Requests\Settings\AccountUpdateRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/accounts', [
            'accountGroups' => Account::groupedForSettings(),
            'accountTypes' => collect(AccountType::cases())->map(fn (AccountType $type) => [
                'value' => $type->value,
                'label' => $this->typeLabel($type),
            ])->values(),
        ]);
    }

    public function store(AccountStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $displayOrder = $data['display_order'] ?? ((int) Account::max('display_order') + 1);

        Account::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'display_order' => $displayOrder,
        ]);

        return redirect()->route('accounts.edit')->with('success', '勘定科目を追加しました。');
    }

    public function update(AccountUpdateRequest $request, Account $account): RedirectResponse
    {
        $account->update($request->validated());

        return redirect()->route('accounts.edit')->with('success', '勘定科目を更新しました。');
    }

    public function destroy(Account $account): RedirectResponse
    {
        if ($account->isInUse()) {
            return redirect()->route('accounts.edit')->withErrors(['account' => '仕訳等で使用中の勘定科目は削除できません。']);
        }

        $account->delete();

        return redirect()->route('accounts.edit')->with('success', '勘定科目を削除しました。');
    }

    private function typeLabel(AccountType $type): string
    {
        return match ($type) {
            AccountType::Asset => '資産',
            AccountType::Liability => '負債',
            AccountType::Equity => '純資産',
            AccountType::Revenue => '収益',
            AccountType::Expense => '費用',
        };
    }
}
