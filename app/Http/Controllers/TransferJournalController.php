<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Enums\ConsumptionTaxCategory;
use App\Enums\JournalSource;
use App\Http\Requests\StoreTransferJournalRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferJournalController extends Controller
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
                ->where('source', JournalSource::Transfer)
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
                    'lines' => $entry->lines->map(fn ($line) => [
                        'account_name' => $line->account?->name ?? '',
                        'debit' => $line->debit,
                        'credit' => $line->credit,
                        'consumption_tax_category' => $line->consumption_tax_category?->value
                            ?? $entry->consumption_tax_category?->value,
                    ])->values()->all(),
                ]);
        }

        return Inertia::render('other/transfer-journal', [
            'entries' => $entries,
            'accountGroups' => Account::groupedForSelect(),
            'presets' => $this->resolvePresets(),
            'transferTaxCategories' => ConsumptionTaxCategory::optionsForTransfer(),
            'hasActiveFiscalYear' => $activeFiscalYear !== null,
        ]);
    }

    public function store(StoreTransferJournalRequest $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $validated = $request->validated();

        $lines = array_map(function (array $line): array {
            return [
                'account_id' => (int) $line['account_id'],
                'debit' => (int) $line['debit'],
                'credit' => (int) $line['credit'],
                'consumption_tax_category' => ConsumptionTaxCategory::from($line['consumption_tax_category']),
            ];
        }, $validated['lines']);

        $this->journalService->createBalancedEntry(
            $company,
            Carbon::parse($validated['entry_date']),
            $validated['description'],
            JournalSource::Transfer,
            $lines,
            null,
            null,
            null,
        );

        return back()->with('success', '振替伝票を登録しました。');
    }

    public function destroy(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        if ($journalEntry->company_id !== $company->id || $journalEntry->source !== JournalSource::Transfer) {
            abort(404);
        }

        $journalEntry->delete();

        return back()->with('success', '振替伝票を削除しました。');
    }

    /**
     * @return list<array{id: string, label: string, debit_account_id: int, credit_account_id: int, description: string}>
     */
    public static function resolvePresets(): array
    {
        $presets = [];

        foreach (config('transfer_presets', []) as $preset) {
            $debitAccount = Account::where('name', $preset['debit_account'])->first();
            $creditAccount = Account::where('name', $preset['credit_account'])->first();

            if ($debitAccount === null || $creditAccount === null) {
                continue;
            }

            $presets[] = [
                'id' => $preset['id'],
                'label' => $preset['label'],
                'debit_account_id' => $debitAccount->id,
                'credit_account_id' => $creditAccount->id,
                'description' => $preset['description'],
            ];
        }

        return $presets;
    }
}
