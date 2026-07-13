<?php

use App\Http\Controllers\AdvanceExpenseController;
use App\Http\Controllers\BankImportController;
use App\Http\Controllers\CreditCardImportController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\ReceiptScanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransferJournalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    if ($request->user()) {
        return Inertia::render('home');
    }

    return Inertia::render('guest-landing');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', fn () => redirect()->route('home'))->name('dashboard');

    Route::get('bank-import', [BankImportController::class, 'index'])->name('bank-import');
    Route::get('bank-import/history', [BankImportController::class, 'history'])->name('bank-import.history');
    Route::post('bank-import', [BankImportController::class, 'store'])->name('bank-import.store');
    Route::get('bank-import/{bankImport}', [BankImportController::class, 'show'])->name('bank-import.show');
    Route::get('bank-import/{bankImport}/review', [BankImportController::class, 'review'])->name('bank-import.review');
    Route::post('bank-import/{bankImport}/confirm', [BankImportController::class, 'confirm'])->name('bank-import.confirm');
    Route::patch('bank-import/rows/{row}', [BankImportController::class, 'updateRow'])->name('bank-import.rows.update');
    Route::post('bank-import/rows/{row}/skip', [BankImportController::class, 'skipRow'])->name('bank-import.rows.skip');

    Route::get('credit-card-import', [CreditCardImportController::class, 'index'])->name('credit-card-import');
    Route::get('credit-card-import/history', [CreditCardImportController::class, 'history'])->name('credit-card-import.history');
    Route::post('credit-card-import', [CreditCardImportController::class, 'store'])->name('credit-card-import.store');
    Route::get('credit-card-import/{import}', [CreditCardImportController::class, 'show'])->name('credit-card-import.show');
    Route::get('credit-card-import/{import}/review', [CreditCardImportController::class, 'review'])->name('credit-card-import.review');
    Route::post('credit-card-import/{import}/confirm', [CreditCardImportController::class, 'confirm'])->name('credit-card-import.confirm');
    Route::patch('credit-card-import/rows/{row}', [CreditCardImportController::class, 'updateRow'])->name('credit-card-import.rows.update');
    Route::post('credit-card-import/rows/{row}/skip', [CreditCardImportController::class, 'skipRow'])->name('credit-card-import.rows.skip');

    Route::get('advance-expenses', [AdvanceExpenseController::class, 'index'])->name('advance-expenses');
    Route::post('advance-expenses', [AdvanceExpenseController::class, 'store'])->name('advance-expenses.store');
    Route::patch('advance-expenses/{journalEntry}', [AdvanceExpenseController::class, 'update'])->name('advance-expenses.update');
    Route::delete('advance-expenses/{journalEntry}', [AdvanceExpenseController::class, 'destroy'])->name('advance-expenses.destroy');

    Route::post('receipt-scans', [ReceiptScanController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('receipt-scans.store');

    Route::get('reports', [ReportController::class, 'index'])->name('reports');
    Route::get('reports/{type}/export/{format}', [ReportController::class, 'export'])->name('reports.export');

    Route::get('other', fn () => Inertia::render('other/index'))->name('other');

    Route::get('other/transfer-journal', [TransferJournalController::class, 'index'])->name('transfer-journal.index');
    Route::post('other/transfer-journal', [TransferJournalController::class, 'store'])->name('transfer-journal.store');
    Route::delete('other/transfer-journal/{journalEntry}', [TransferJournalController::class, 'destroy'])->name('transfer-journal.destroy');

    Route::get('journals', [JournalController::class, 'index'])->name('journals.index');
    Route::patch('journals/{journalEntry}', [JournalController::class, 'update'])->name('journals.update');
    Route::delete('journals/bulk', [JournalController::class, 'destroyBulk'])->name('journals.destroy-bulk');
    Route::delete('journals/{journalEntry}', [JournalController::class, 'destroy'])->name('journals.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/company.php';
require __DIR__.'/auth.php';
