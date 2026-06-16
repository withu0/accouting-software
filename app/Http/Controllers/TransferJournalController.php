<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Enums\JournalSource;
use App\Http\Requests\StoreTransferJournalRequest;
use App\Models\Account;
use App\Models\Company;
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
                    'debit_account_name' => $entry->lines->first(fn ($line) => $line->debit > 0)?->account?->name ?? '',
                    'credit_account_name' => $entry->lines->first(fn ($line) => $line->credit > 0)?->account?->name ?? '',
                ]);
        }

        return Inertia::render('other/transfer-journal', [
            'entries' => $entries,
            'accountGroups' => Account::groupedForSelect(),
            'presets' => $this->resolvePresets(),
            'hasActiveFiscalYear' => $activeFiscalYear !== null,
        ]);
    }

    public function store(StoreTransferJournalRequest $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $validated = $request->validated();
        $amount = (int) $validated['debit_amount'];

        $this->journalService->createBalancedEntry(
            $company,
            Carbon::parse($validated['entry_date']),
            $validated['description'],
            JournalSource::Transfer,
            [
                ['account_id' => (int) $validated['debit_account_id'], 'debit' => $amount, 'credit' => 0],
                ['account_id' => (int) $validated['credit_account_id'], 'debit' => 0, 'credit' => $amount],
            ],
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
