<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Enums\JournalSource;
use App\Models\JournalEntry;
use App\Services\BankImportService;
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
    ) {}

    public function index(Request $request): Response
    {
        $company = $this->resolveCompany($request);

        $activeFiscalYear = $company->activeFiscalYear();

        $query = $company->journalEntries()
            ->with('lines')
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($activeFiscalYear !== null) {
            $query->where('fiscal_year_id', $activeFiscalYear->id);
        } else {
            $query->whereRaw('1 = 0');
        }

        $entries = $query->paginate(25)->through(fn ($entry) => [
            'id' => $entry->id,
            'entry_date' => $entry->entry_date->format('Y-m-d'),
            'description' => $entry->description,
            'source' => $entry->source->value,
            'total_amount' => $entry->lines->sum('debit'),
        ]);

        return Inertia::render('journals/index', [
            'entries' => $entries,
        ]);
    }

    public function destroy(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        if ($journalEntry->company_id !== $company->id || $journalEntry->source !== JournalSource::BankCsv) {
            abort(404);
        }

        try {
            $this->bankImportService->deletePostedJournal($company, $journalEntry);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['journal' => $e->getMessage()]);
        }

        return back()->with('success', '銀行CSV取込の仕訳を削除しました。');
    }
}
