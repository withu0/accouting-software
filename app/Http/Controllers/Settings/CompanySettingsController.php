<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CompanyUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanySettingsController extends Controller
{
    use ResolvesCompany;

    public function update(CompanyUpdateRequest $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        $company->update($request->validated());

        return back();
    }
}
