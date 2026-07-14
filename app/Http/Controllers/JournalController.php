<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Enums\ConsumptionTaxCategory;
use App\Enums\JournalSource;
use App\Http\Requests\UpdateBankImportJournalRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\BankImportService;
use App\Services\CreditCardImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class JournalController extends Controller
{
    use ResolvesCompany;

    public function __construct(
        private readonly BankImportService $bankImportService,
        private readonly CreditCardImportService $creditCardImportService,
    ) {}

    public function index(Request $request): Response
    {
        $company = $this->resolveCompany($request);

        $activeFiscalYear = $company->activeFiscalYear();

        $query = $company->journalEntries()
            ->with(['lines.account', 'bankImportRow'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($activeFiscalYear !== null) {
            $query->where('fiscal_year_id', $activeFiscalYear->id);
        } else {
            $query->whereRaw('1 = 0');
        }

        $entries = $query->paginate(25)->through(function ($entry) {
            $data = [
                'id' => $entry->id,
                'entry_date' => $entry->entry_date->format('Y-m-d'),
                'description' => $entry->description,
                'source' => $entry->source->value,
                'total_amount' => $entry->lines->sum('debit'),
                'debit_account_name' => $entry->lines->first(fn ($line) => $line->debit > 0)?->account?->name ?? '',
                'credit_account_name' => $entry->lines->first(fn ($line) => $line->credit > 0)?->account?->name ?? '',
            ];

            if ($entry->source === JournalSource::BankCsv && $entry->bankImportRow !== null) {
                $row = $entry->bankImportRow;
                $isDeposit = $row->deposit_amount > 0;

                try {
                    $accountId = $this->bankImportService->resolveCounterAccountId($entry, $row);
                } catch (InvalidArgumentException) {
                    $accountId = $isDeposit ? Account::findByName('売上高')->id : null;
                }

                $data['bank_csv_edit'] = [
                    'row_id' => $row->id,
                    'is_deposit' => $isDeposit,
                    'amount' => $isDeposit ? $row->deposit_amount : $row->withdrawal_amount,
                    'account_id' => $accountId,
                    'consumption_tax_category' => $entry->consumption_tax_category?->value
                        ?? ($isDeposit ? ConsumptionTaxCategory::TaxableSales10->value : ConsumptionTaxCategory::TaxablePurchase10->value),
                    'has_qualified_invoice' => $entry->has_qualified_invoice ?? true,
                ];
            }

            return $data;
        });

        return Inertia::render('journals/index', [
            'entries' => $entries,
            'accountGroups' => Account::groupedForSelect(),
            'expenseAccounts' => Account::expenseAccounts()->map(fn (Account $account) => [
                'id' => $account->id,
                'default_consumption_tax_category' => $account->default_consumption_tax_category?->value,
            ]),
            'salesTaxCategories' => ConsumptionTaxCategory::optionsForSales(),
            'purchaseTaxCategories' => ConsumptionTaxCategory::optionsForPurchases(),
            'hasActiveFiscalYear' => $activeFiscalYear !== null,
        ]);
    }

    public function destroy(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        if ($journalEntry->company_id !== $company->id) {
            abort(404);
        }

        try {
            if ($journalEntry->source === JournalSource::BankCsv) {
                $this->bankImportService->deletePostedJournal($company, $journalEntry);

                return back()->with('success', '銀行CSV取込の仕訳を削除しました。');
            }

            if ($journalEntry->source === JournalSource::CreditCardCsv) {
                $this->creditCardImportService->deletePostedJournal($company, $journalEntry);

                return back()->with('success', 'クレジットカードCSV取込の仕訳を削除しました。');
            }
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['journal' => $e->getMessage()]);
        }

        abort(404);
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $entries = $company->journalEntries()
            ->whereIn('id', $validated['ids'])
            ->whereIn('source', [JournalSource::BankCsv, JournalSource::CreditCardCsv])
            ->get();

        if ($entries->count() !== count($validated['ids'])) {
            abort(404);
        }

        $bankEntries = $entries->filter(fn (JournalEntry $entry) => $entry->source === JournalSource::BankCsv);
        $creditCardEntries = $entries->filter(fn (JournalEntry $entry) => $entry->source === JournalSource::CreditCardCsv);

        try {
            if ($bankEntries->isNotEmpty()) {
                $this->bankImportService->deletePostedJournals($company, $bankEntries);
            }

            if ($creditCardEntries->isNotEmpty()) {
                $this->creditCardImportService->deletePostedJournals($company, $creditCardEntries);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['journal' => $e->getMessage()]);
        }

        $count = $entries->count();

        return back()->with('success', "{$count}件のCSV取込仕訳を削除しました。");
    }

    public function update(UpdateBankImportJournalRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        if ($journalEntry->company_id !== $company->id || $journalEntry->source !== JournalSource::BankCsv) {
            abort(404);
        }

        $row = $journalEntry->bankImportRow;
        if ($row === null) {
            abort(404);
        }

        $validated = $request->validated();

        try {
            $this->bankImportService->updateRow($company, $row, $validated);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['journal' => $e->getMessage()]);
        }

        return back()->with('success', '仕訳を更新しました。');
    }
}
