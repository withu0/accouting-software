<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    use ResolvesCompany;

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
}
