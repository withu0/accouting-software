<?php

use App\Http\Controllers\Settings\CompanySettingsController;
use App\Http\Controllers\Settings\FiscalYearSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::patch('settings/company', [CompanySettingsController::class, 'update'])
        ->name('company.update');

    Route::get('settings/fiscal-year', [FiscalYearSettingsController::class, 'edit'])
        ->name('fiscal-year.edit');
    Route::post('settings/fiscal-year', [FiscalYearSettingsController::class, 'store'])
        ->name('fiscal-year.store');
    Route::patch('settings/fiscal-year/{fiscalYear}', [FiscalYearSettingsController::class, 'update'])
        ->name('fiscal-year.update');
});
