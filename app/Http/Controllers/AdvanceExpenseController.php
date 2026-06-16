<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Enums\JournalSource;
use App\Http\Requests\StoreAdvanceExpenseRequest;
use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdvanceExpenseController extends Controller
{
    use ResolvesCompany;

    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    public function index(Request $request): Response
    {
        $company = $this->resolveCompany($request);
        $activeFiscalYear = $company->activeFiscalYear();

        $entries = collect();
        if ($activeFiscalYear !== null) {
            $entries = $company->journalEntries()
                ->where('source', JournalSource::AdvanceExpense)
                ->where('fiscal_year_id', $activeFiscalYear->id)
                ->with(['lines.account'])
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (JournalEntry $entry) => [
                    'id' => $entry->id,
                    'entry_date' => $entry->entry_date->format('Y-m-d'),
                    'description' => $entry->description,
                    'amount' => $entry->lines->sum('debit'),
                    'account_name' => $entry->lines->first(fn ($line) => $line->debit > 0)?->account?->name ?? '',
                ]);
        }

        $expenseAccounts = Account::expenseAccounts()->map(fn (Account $account) => [
            'id' => $account->id,
            'name' => $account->name,
        ]);

        return Inertia::render('advance-expenses/index', [
            'entries' => $entries,
            'expenseAccounts' => $expenseAccounts,
            'hasActiveFiscalYear' => $activeFiscalYear !== null,
        ]);
    }

    public function store(StoreAdvanceExpenseRequest $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $validated = $request->validated();

        $expenseAccount = Account::findOrFail($validated['account_id']);
        $officerLoanAccount = Account::findByName('役員借入金');
        $amount = (int) $validated['amount'];

        $this->journalService->createBalancedEntry(
            $company,
            Carbon::parse($validated['entry_date']),
            $validated['description'],
            JournalSource::AdvanceExpense,
            [
                ['account_id' => $expenseAccount->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $officerLoanAccount->id, 'debit' => 0, 'credit' => $amount],
            ],
        );

        return back()->with('success', '立替経費を登録しました。');
    }

    public function destroy(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        if ($journalEntry->company_id !== $company->id || $journalEntry->source !== JournalSource::AdvanceExpense) {
            abort(404);
        }

        $journalEntry->delete();

        return back()->with('success', '立替経費を削除しました。');
    }
}
