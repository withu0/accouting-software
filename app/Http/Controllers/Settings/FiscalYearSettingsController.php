<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FiscalYearRequest;
use App\Models\Company;
use App\Models\FiscalYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FiscalYearSettingsController extends Controller
{
    use ResolvesCompany;

    public function edit(Request $request): Response
    {
        $company = $this->resolveCompany($request);

        return Inertia::render('settings/fiscal-year', [
            'company' => $company->only('id', 'name', 'representative_name', 'address'),
            'fiscalYears' => $company->fiscalYears()->orderByDesc('start_date')->get(),
            'activeFiscalYear' => $company->activeFiscalYear(),
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
