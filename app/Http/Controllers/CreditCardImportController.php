<?php

namespace App\Http\Controllers;

use App\Enums\ConsumptionTaxCategory;
use App\Enums\CreditCardImportRowStatus;
use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Http\Requests\ConfirmCreditCardImportRequest;
use App\Http\Requests\StoreCreditCardImportRequest;
use App\Http\Requests\UpdateCreditCardImportRowRequest;
use App\Models\Account;
use App\Models\Company;
use App\Models\CreditCardImport;
use App\Models\CreditCardImportRow;
use App\Services\CreditCardImportService;
use App\Services\DescriptionRuleMatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CreditCardImportController extends Controller
{
    use ResolvesCompany;

    public function __construct(
        private readonly CreditCardImportService $creditCardImportService,
        private readonly DescriptionRuleMatcher $ruleMatcher,
    ) {}

    public function index(Request $request): Response
    {
        $company = $this->resolveCompany($request);

        $recentImports = $company->creditCardImports()
            ->withCount([
                'rows as confirmed_count' => fn ($query) => $query->where('status', CreditCardImportRowStatus::Confirmed),
                'rows as pending_count' => fn ($query) => $query->where('status', CreditCardImportRowStatus::Pending),
                'rows as skipped_count' => fn ($query) => $query->where('status', CreditCardImportRowStatus::Skipped),
            ])
            ->orderByDesc('imported_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (CreditCardImport $import) => [
                'id' => $import->id,
                'original_filename' => $import->original_filename,
                'detected_format' => $import->detected_format?->label(),
                'card_name' => $import->card_name,
                'status' => $import->status->value,
                'row_count' => $import->row_count,
                'confirmed_count' => $import->confirmed_count,
                'pending_count' => $import->pending_count,
                'skipped_count' => $import->skipped_count,
                'imported_at' => $import->imported_at->format('Y-m-d H:i'),
            ])
            ->values()
            ->all();

        return Inertia::render('credit-card-import/index', [
            'hasActiveFiscalYear' => $company->activeFiscalYear() !== null,
            'recentImports' => $recentImports,
        ]);
    }

    public function store(StoreCreditCardImportRequest $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        if ($company->activeFiscalYear() === null) {
            return back()->withErrors(['file' => '会計期間が設定されていません。先に会計期間を設定してください。']);
        }

        try {
            $result = $this->creditCardImportService->import($company, $request->file('file'));
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('credit-card-import')
                ->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()
            ->route('credit-card-import.review', $result['import'])
            ->with('importSummary', [
                'total' => $result['total'],
                'new' => $result['new'],
                'duplicates' => $result['duplicates'],
                'out_of_period' => $result['out_of_period'],
                'detected_format' => isset($result['detected_format'])
                    ? $result['detected_format']->label()
                    : null,
                'card_name' => $result['import']->card_name,
                'payment_date' => $result['import']->payment_date?->format('Y-m-d'),
                'billing_amount' => $result['import']->billing_amount,
            ]);
    }

    public function review(Request $request, CreditCardImport $import): Response
    {
        $company = $this->resolveCompany($request);
        $this->authorizeImport($company, $import);

        $pendingRows = $import->rows()
            ->where('status', CreditCardImportRowStatus::Pending)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(function (CreditCardImportRow $row) use ($company) {
                $suggestedAccount = $this->ruleMatcher->suggestAccount($company, $row->description);
                $suggestedAccountId = $suggestedAccount?->id ?? $row->suggested_account_id;
                $accountId = $suggestedAccountId;

                return [
                    'id' => $row->id,
                    'transaction_date' => $row->transaction_date->format('Y-m-d'),
                    'description' => $row->description,
                    'amount' => $row->amount,
                    'suggested_account_id' => $suggestedAccountId,
                    'account_id' => $accountId,
                    'consumption_tax_category' => null,
                    'has_qualified_invoice' => true,
                ];
            });

        $expenseAccounts = Account::expenseAccounts()->map(fn (Account $account) => [
            'id' => $account->id,
            'name' => $account->name,
            'default_consumption_tax_category' => $account->default_consumption_tax_category?->value,
        ]);

        return Inertia::render('credit-card-import/review', [
            'creditCardImport' => [
                'id' => $import->id,
                'original_filename' => $import->original_filename,
                'card_name' => $import->card_name,
                'payment_date' => $import->payment_date?->format('Y-m-d'),
                'billing_amount' => $import->billing_amount,
            ],
            'rows' => $pendingRows,
            'expenseAccounts' => $expenseAccounts,
            'accountGroups' => Account::groupedForSelect(),
            'purchaseTaxCategories' => ConsumptionTaxCategory::optionsForPurchases(),
            'hasActiveFiscalYear' => $company->activeFiscalYear() !== null,
            'importSummary' => session('importSummary'),
        ]);
    }

    public function confirm(ConfirmCreditCardImportRequest $request, CreditCardImport $import): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $this->authorizeImport($company, $import);

        try {
            $this->creditCardImportService->confirmRows($company, $import, $request->validated('rows'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['rows' => $e->getMessage()]);
        } catch (ModelNotFoundException $e) {
            return back()->withErrors(['rows' => '記帳に必要な勘定科目が見つかりません。']);
        }

        $pendingCount = $import->rows()->where('status', CreditCardImportRowStatus::Pending)->count();

        if ($pendingCount > 0) {
            return redirect()
                ->route('credit-card-import.review', $import)
                ->with('success', '選択した取引を記帳しました。');
        }

        return redirect()
            ->route('credit-card-import')
            ->with('success', 'すべての取引の記帳が完了しました。');
    }

    public function skipRow(Request $request, CreditCardImportRow $row): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $this->authorizeImport($company, $row->creditCardImport);

        try {
            $this->creditCardImportService->skipRow($company, $row);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['row' => $e->getMessage()]);
        }

        $pendingCount = $row->creditCardImport->rows()->where('status', CreditCardImportRowStatus::Pending)->count();

        if ($pendingCount > 0) {
            return back()->with('success', '取引をスキップしました。');
        }

        return redirect()
            ->route('credit-card-import')
            ->with('success', 'すべての取引の処理が完了しました。');
    }

    public function history(Request $request): Response
    {
        $company = $this->resolveCompany($request);

        $imports = $company->creditCardImports()
            ->withCount([
                'rows as confirmed_count' => fn ($query) => $query->where('status', CreditCardImportRowStatus::Confirmed),
                'rows as pending_count' => fn ($query) => $query->where('status', CreditCardImportRowStatus::Pending),
                'rows as skipped_count' => fn ($query) => $query->where('status', CreditCardImportRowStatus::Skipped),
            ])
            ->orderByDesc('imported_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->through(fn (CreditCardImport $import) => [
                'id' => $import->id,
                'original_filename' => $import->original_filename,
                'detected_format' => $import->detected_format?->label(),
                'card_name' => $import->card_name,
                'status' => $import->status->value,
                'row_count' => $import->row_count,
                'confirmed_count' => $import->confirmed_count,
                'pending_count' => $import->pending_count,
                'skipped_count' => $import->skipped_count,
                'imported_at' => $import->imported_at->format('Y-m-d H:i'),
            ]);

        return Inertia::render('credit-card-import/history', [
            'imports' => $imports,
            'hasActiveFiscalYear' => $company->activeFiscalYear() !== null,
        ]);
    }

    public function show(Request $request, CreditCardImport $import): Response
    {
        $company = $this->resolveCompany($request);
        $this->authorizeImport($company, $import);

        $rows = $import->rows()
            ->with('journalEntry')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(function (CreditCardImportRow $row) use ($company) {
                $accountId = null;

                if ($row->status === CreditCardImportRowStatus::Confirmed && $row->journalEntry !== null) {
                    $accountId = $this->creditCardImportService->resolveCounterAccountId($row->journalEntry, $row);
                } elseif ($row->suggested_account_id !== null) {
                    $accountId = $row->suggested_account_id;
                }

                return [
                    'id' => $row->id,
                    'transaction_date' => $row->transaction_date->format('Y-m-d'),
                    'description' => $row->description,
                    'amount' => $row->amount,
                    'status' => $row->status->value,
                    'account_id' => $accountId,
                    'journal_entry_id' => $row->journal_entry_id,
                    'consumption_tax_category' => $row->journalEntry?->consumption_tax_category?->value,
                    'has_qualified_invoice' => $row->journalEntry?->has_qualified_invoice ?? true,
                ];
            });

        return Inertia::render('credit-card-import/show', [
            'creditCardImport' => [
                'id' => $import->id,
                'original_filename' => $import->original_filename,
                'card_name' => $import->card_name,
                'payment_date' => $import->payment_date?->format('Y-m-d'),
                'billing_amount' => $import->billing_amount,
                'status' => $import->status->value,
                'imported_at' => $import->imported_at->format('Y-m-d H:i'),
            ],
            'rows' => $rows,
            'accountGroups' => Account::groupedForSelect(),
            'purchaseTaxCategories' => ConsumptionTaxCategory::optionsForPurchases(),
            'hasActiveFiscalYear' => $company->activeFiscalYear() !== null,
        ]);
    }

    public function updateRow(UpdateCreditCardImportRowRequest $request, CreditCardImportRow $row): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $this->authorizeImport($company, $row->creditCardImport);

        $validated = $request->validated();

        try {
            $this->creditCardImportService->updateRow($company, $row, $validated);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['row' => $e->getMessage()]);
        }

        return back()->with('success', '取引を更新しました。');
    }

    private function authorizeImport(Company $company, CreditCardImport $import): void
    {
        if ($import->company_id !== $company->id) {
            abort(404);
        }
    }
}
