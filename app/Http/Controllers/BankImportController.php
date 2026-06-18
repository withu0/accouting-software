<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Enums\BankImportRowStatus;
use App\Http\Requests\ConfirmBankImportRequest;
use App\Http\Requests\StoreBankImportRequest;
use App\Models\Account;
use App\Models\BankImport;
use App\Models\BankImportRow;
use App\Models\Company;
use App\Services\BankImportService;
use App\Services\DescriptionRuleMatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class BankImportController extends Controller
{
    use ResolvesCompany;

    public function __construct(
        private readonly BankImportService $bankImportService,
        private readonly DescriptionRuleMatcher $ruleMatcher,
    ) {}

    public function index(Request $request): Response
    {
        $company = $this->resolveCompany($request);

        return Inertia::render('bank-import/index', [
            'hasActiveFiscalYear' => $company->activeFiscalYear() !== null,
        ]);
    }

    public function store(StoreBankImportRequest $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        if ($company->activeFiscalYear() === null) {
            return back()->withErrors(['file' => '会計期間が設定されていません。先に会計期間を設定してください。']);
        }

        try {
            $result = $this->bankImportService->import($company, $request->file('file'));
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('bank-import')
                ->withErrors(['file' => $e->getMessage()]);
        }

        $redirect = redirect()
            ->route('bank-import.review', $result['import'])
            ->with('importSummary', [
                'total' => $result['total'],
                'new' => $result['new'],
                'duplicates' => $result['duplicates'],
                'out_of_period' => $result['out_of_period'],
            ]);

        if ($result['resumed'] ?? false) {
            $redirect->with('success', '未完了の取込があります。記帳を続けてください。');
        }

        return $redirect;
    }

    public function review(Request $request, BankImport $bankImport): Response
    {
        $company = $this->resolveCompany($request);
        $this->authorizeImport($company, $bankImport);

        $pendingRows = $bankImport->rows()
            ->where('status', BankImportRowStatus::Pending)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(function (BankImportRow $row) use ($company) {
                $suggestedAccountId = null;
                if ($row->withdrawal_amount > 0) {
                    $suggestedAccount = $this->ruleMatcher->suggestAccount($company, $row->description);
                    $suggestedAccountId = $suggestedAccount?->id;
                }

                return [
                    'id' => $row->id,
                    'transaction_date' => $row->transaction_date->format('Y-m-d'),
                    'description' => $row->description,
                    'deposit_amount' => $row->deposit_amount,
                    'withdrawal_amount' => $row->withdrawal_amount,
                    'is_deposit' => $row->deposit_amount > 0,
                    'suggested_account_id' => $suggestedAccountId,
                ];
            });

        $expenseAccounts = Account::expenseAccounts()->map(fn (Account $account) => [
            'id' => $account->id,
            'name' => $account->name,
        ]);

        return Inertia::render('bank-import/review', [
            'bankImport' => [
                'id' => $bankImport->id,
                'original_filename' => $bankImport->original_filename,
            ],
            'rows' => $pendingRows,
            'expenseAccounts' => $expenseAccounts,
            'importSummary' => session('importSummary'),
        ]);
    }

    public function confirm(ConfirmBankImportRequest $request, BankImport $bankImport): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $this->authorizeImport($company, $bankImport);

        try {
            $this->bankImportService->confirmRows($company, $bankImport, $request->validated('rows'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['rows' => $e->getMessage()]);
        } catch (ModelNotFoundException $e) {
            return back()->withErrors(['rows' => '記帳に必要な勘定科目が見つかりません。']);
        }

        $pendingCount = $bankImport->rows()->where('status', BankImportRowStatus::Pending)->count();

        if ($pendingCount > 0) {
            return redirect()
                ->route('bank-import.review', $bankImport)
                ->with('success', '選択した取引を記帳しました。');
        }

        return redirect()
            ->route('bank-import')
            ->with('success', 'すべての取引の記帳が完了しました。');
    }

    public function skipRow(Request $request, BankImportRow $row): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $this->authorizeImport($company, $row->bankImport);

        try {
            $this->bankImportService->skipRow($company, $row);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['row' => $e->getMessage()]);
        }

        $pendingCount = $row->bankImport->rows()->where('status', BankImportRowStatus::Pending)->count();

        if ($pendingCount > 0) {
            return back()->with('success', '取引をスキップしました。');
        }

        return redirect()
            ->route('bank-import')
            ->with('success', 'すべての取引の処理が完了しました。');
    }

    private function authorizeImport(Company $company, BankImport $bankImport): void
    {
        if ($bankImport->company_id !== $company->id) {
            abort(404);
        }
    }
}
