<?php

namespace App\Http\Controllers;

use App\Enums\ConsumptionTaxCategory;
use App\Enums\JournalSource;
use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Http\Requests\StoreAdvanceExpenseRequest;
use App\Http\Requests\UpdateAdvanceExpenseRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\ConsumptionTaxService;
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
        private readonly ConsumptionTaxService $consumptionTaxService,
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
                ->map(function (JournalEntry $entry) {
                    $expenseLine = $entry->lines->first(
                        fn ($line) => $line->debit > 0 && $line->account?->name !== '仮払消費税',
                    );

                    return [
                        'id' => $entry->id,
                        'entry_date' => $entry->entry_date->format('Y-m-d'),
                        'description' => $entry->description,
                        'amount' => $entry->lines->sum('debit'),
                        'account_id' => $expenseLine?->account_id ?? 0,
                        'account_name' => $expenseLine?->account?->name ?? '',
                        'consumption_tax_category' => $entry->consumption_tax_category?->value,
                        'has_qualified_invoice' => $entry->has_qualified_invoice,
                    ];
                });
        }

        $expenseAccounts = Account::expenseAccounts()->map(fn (Account $account) => [
            'id' => $account->id,
            'name' => $account->name,
            'default_consumption_tax_category' => $account->default_consumption_tax_category?->value,
        ]);

        return Inertia::render('advance-expenses/index', [
            'entries' => $entries,
            'expenseAccounts' => $expenseAccounts,
            'purchaseTaxCategories' => ConsumptionTaxCategory::optionsForPurchases(),
            'hasActiveFiscalYear' => $activeFiscalYear !== null,
            'receiptScanAvailable' => is_string(config('services.openai.key')) && config('services.openai.key') !== '',
        ]);
    }

    public function store(StoreAdvanceExpenseRequest $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $validated = $request->validated();

        $expenseAccount = Account::findOrFail($validated['account_id']);
        $officerLoanAccount = Account::findByName('役員借入金');
        $amount = (int) $validated['amount'];
        $entryDate = Carbon::parse($validated['entry_date']);
        $baseCategory = ConsumptionTaxCategory::from($validated['consumption_tax_category']);
        $hasQualifiedInvoice = $baseCategory->isBasePurchase()
            ? (bool) ($validated['has_qualified_invoice'] ?? true)
            : null;
        $effectiveCategory = $this->consumptionTaxService->resolveEffectiveCategory(
            $baseCategory,
            $hasQualifiedInvoice,
            $entryDate,
        );

        $this->journalService->createBalancedEntry(
            $company,
            $entryDate,
            $validated['description'],
            JournalSource::AdvanceExpense,
            $this->consumptionTaxService->buildJournalLines(
                $effectiveCategory,
                $amount,
                $expenseAccount->id,
                $officerLoanAccount->id,
                false,
            ),
            null,
            $baseCategory,
            $hasQualifiedInvoice,
        );

        return back()->with('success', '立替経費を登録しました。');
    }

    public function update(UpdateAdvanceExpenseRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        if ($journalEntry->company_id !== $company->id || $journalEntry->source !== JournalSource::AdvanceExpense) {
            abort(404);
        }

        $validated = $request->validated();

        $expenseAccount = Account::findOrFail($validated['account_id']);
        $officerLoanAccount = Account::findByName('役員借入金');
        $amount = (int) $validated['amount'];
        $entryDate = Carbon::parse($validated['entry_date']);
        $baseCategory = ConsumptionTaxCategory::from($validated['consumption_tax_category']);
        $hasQualifiedInvoice = $baseCategory->isBasePurchase()
            ? (bool) ($validated['has_qualified_invoice'] ?? true)
            : null;
        $effectiveCategory = $this->consumptionTaxService->resolveEffectiveCategory(
            $baseCategory,
            $hasQualifiedInvoice,
            $entryDate,
        );

        $this->journalService->updateBalancedEntry(
            $journalEntry,
            $company,
            $entryDate,
            $validated['description'],
            $this->consumptionTaxService->buildJournalLines(
                $effectiveCategory,
                $amount,
                $expenseAccount->id,
                $officerLoanAccount->id,
                false,
            ),
            $baseCategory,
            $hasQualifiedInvoice,
        );

        return back()->with('success', '立替経費を更新しました。');
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
