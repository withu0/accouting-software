<?php

namespace App\Http\Controllers\Settings;

use App\Enums\TaxEntityStatus;
use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TaxEntityStatusUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsumptionTaxSettingsController extends Controller
{
    use ResolvesCompany;

    public function edit(Request $request): Response
    {
        $company = $this->resolveCompany($request);

        return Inertia::render('other/consumption-tax', [
            'taxEntityStatus' => ($company->tax_entity_status ?? TaxEntityStatus::Taxable)->value,
            'taxEntityStatusOptions' => TaxEntityStatus::options(),
        ]);
    }

    public function update(TaxEntityStatusUpdateRequest $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        $company->update($request->validated());

        return back()->with('success', '消費税設定を保存しました。');
    }
}
