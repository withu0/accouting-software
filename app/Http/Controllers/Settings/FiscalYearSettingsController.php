<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ConsumptionTaxMethod;
use App\Enums\SimplifiedTaxIndustry;
use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FiscalYearRequest;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Services\YearEndCloseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class FiscalYearSettingsController extends Controller
{
    use ResolvesCompany;

    public function __construct(
        private readonly YearEndCloseService $yearEndCloseService,
    ) {}

    public function edit(Request $request): Response
    {
        $company = $this->resolveCompany($request);
        $activeFiscalYear = $company->activeFiscalYear();

        return Inertia::render('settings/fiscal-year', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'representative_name' => $company->representative_name,
                'address' => $company->address,
                'consumption_tax_method' => ($company->consumption_tax_method ?? ConsumptionTaxMethod::Standard)->value,
                'simplified_tax_industry' => $company->simplified_tax_industry?->value,
            ],
            'fiscalYears' => $company->fiscalYears()->orderByDesc('start_date')->get()->map(fn (FiscalYear $year) => [
                'id' => $year->id,
                'start_date' => $year->start_date->format('Y-m-d'),
                'end_date' => $year->end_date->format('Y-m-d'),
                'is_active' => $year->is_active,
                'closed_at' => $year->closed_at?->format('Y-m-d H:i'),
            ]),
            'activeFiscalYear' => $activeFiscalYear ? [
                'id' => $activeFiscalYear->id,
                'start_date' => $activeFiscalYear->start_date->format('Y-m-d'),
                'end_date' => $activeFiscalYear->end_date->format('Y-m-d'),
                'is_active' => $activeFiscalYear->is_active,
                'closed_at' => $activeFiscalYear->closed_at?->format('Y-m-d H:i'),
            ] : null,
            'yearEndPreview' => $activeFiscalYear !== null
                ? $this->yearEndCloseService->preview($company, $activeFiscalYear)
                : null,
            'consumptionTaxMethods' => ConsumptionTaxMethod::options(),
            'simplifiedTaxIndustries' => SimplifiedTaxIndustry::options(),
        ]);
    }

    public function store(FiscalYearRequest $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        $this->activateFiscalYear($company, $request->validated());

        return back();
    }

    public function update(FiscalYearRequest $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        if ($fiscalYear->company_id !== $company->id) {
            abort(404);
        }

        $fiscalYear->update($request->validated());
        $this->deactivateOtherFiscalYears($company, $fiscalYear->id);
        $fiscalYear->update(['is_active' => true]);

        return back();
    }

    public function close(Request $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        if ($fiscalYear->company_id !== $company->id) {
            abort(404);
        }

        try {
            $nextYear = $this->yearEndCloseService->close($company, $fiscalYear);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['close' => $e->getMessage()]);
        }

        return back()->with(
            'success',
            '決算を確定し、'.$nextYear->start_date->format('Y-m-d').' 開始の翌期へ残高を引き継ぎました。会計期間は切り替えていません。',
        );
    }

    /**
     * @param  array{start_date: string, end_date: string}  $data
     */
    private function activateFiscalYear(Company $company, array $data): FiscalYear
    {
        $this->deactivateOtherFiscalYears($company);

        return $company->fiscalYears()->create([
            ...$data,
            'is_active' => true,
        ]);
    }

    private function deactivateOtherFiscalYears(Company $company, ?int $exceptId = null): void
    {
        $query = $company->fiscalYears()->where('is_active', true);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_active' => false]);
    }
}
