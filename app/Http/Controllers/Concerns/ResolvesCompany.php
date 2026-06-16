<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use Illuminate\Http\Request;

trait ResolvesCompany
{
    protected function resolveCompany(Request $request): Company
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        return $user->ensureCompany();
    }
}
