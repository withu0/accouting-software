<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Http\Requests\UpsertOpeningBalanceRequest;
use App\Services\OpeningBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class OpeningBalanceController extends Controller
{
    use ResolvesCompany;

    public function __construct(
        private readonly OpeningBalanceService $openingBalanceService,
    ) {}

    public function edit(Request $request): Response
    {
        $company = $this->resolveCompany($request);
        $fiscalYear = $company->activeFiscalYear();

        return Inertia::render('opening-balances/edit', [
            'hasActiveFiscalYear' => $fiscalYear !== null,
            'form' => $fiscalYear !== null
                ? $this->openingBalanceService->formState($company, $fiscalYear)
                : null,
        ]);
    }

    public function save(UpsertOpeningBalanceRequest $request): RedirectResponse
    {
        return $this->persist($request, confirm: false);
    }

    public function confirm(UpsertOpeningBalanceRequest $request): RedirectResponse
    {
        return $this->persist($request, confirm: true);
    }

    private function persist(UpsertOpeningBalanceRequest $request, bool $confirm): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $fiscalYear = $company->activeFiscalYear();

        if ($fiscalYear === null) {
            return back()->withErrors(['fiscal_year' => '会計期間が設定されていません。']);
        }

        try {
            if ($confirm) {
                $this->openingBalanceService->confirm($company, $fiscalYear, $request->validated('lines'));

                return back()->with('success', '開始残高を確定しました。今期の残高に反映されています。');
            }

            $this->openingBalanceService->saveDraft($company, $fiscalYear, $request->validated('lines'));

            return back()->with('success', '開始残高を下書き保存しました。');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['lines' => $e->getMessage()]);
        }
    }
}
