<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Http\Requests\SaveInvoiceRequest;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\DompdfFontRegistrar;
use App\Services\InvoiceService;
use App\Services\QualifiedInvoiceDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InvoiceController extends Controller
{
    use ResolvesCompany;

    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly QualifiedInvoiceDocument $document,
        private readonly DompdfFontRegistrar $dompdfFontRegistrar,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $company = $this->resolveCompany($request);

        $invoices = $company->invoices()
            ->with('lines')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Invoice $invoice) => $this->present($invoice));

        return Inertia::render('invoices/index', [
            'invoices' => $invoices,
            'registrationNumber' => $company->invoice_registration_number,
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $company = $this->resolveCompany($request);

        return Inertia::render('invoices/form', $this->formProps($company, null));
    }

    public function store(SaveInvoiceRequest $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $invoice = $this->invoiceService->create($company, $request->validated());

        return redirect()
            ->route('invoices.edit', $invoice)
            ->with('success', '請求書を保存しました。');
    }

    public function edit(Request $request, Invoice $invoice): InertiaResponse
    {
        $company = $this->resolveCompany($request);
        $invoice = $this->findInvoice($company, $invoice);

        return Inertia::render('invoices/form', $this->formProps($company, $invoice));
    }

    public function update(SaveInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $invoice = $this->findInvoice($company, $invoice);
        $this->invoiceService->update($invoice, $request->validated());

        return back()->with('success', '請求書を保存しました。');
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $invoice = $this->findInvoice($company, $invoice);
        $invoice->delete();

        return redirect()
            ->route('invoices.index')
            ->with('success', '請求書を削除しました。');
    }

    public function pdf(Request $request, Invoice $invoice): Response|RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $invoice = $this->findInvoice($company, $invoice);

        if ($company->invoice_registration_number === null || $company->invoice_registration_number === '') {
            return redirect()
                ->route('fiscal-year.edit')
                ->withErrors([
                    'invoice_registration_number' => '適格請求書を出力するには、登録番号（Tと13桁）を保存してください。',
                ]);
        }

        $this->dompdfFontRegistrar->ensureJapaneseFontRegistered();

        $pdf = Pdf::loadView('invoices.qualified', $this->document->data($invoice, $company))
            ->setPaper('a4', 'portrait');

        return $pdf->download('invoice_'.$invoice->invoice_number.'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Invoice $invoice): array
    {
        $summary = $this->document->summary($invoice);

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'issue_date' => $invoice->issue_date->toDateString(),
            'customer_name' => $invoice->customer_name,
            'notes' => $invoice->notes ?? '',
            'lines' => $summary['lines'],
            'rates' => $summary['rates'],
            'subtotal' => $summary['subtotal'],
            'tax_total' => $summary['tax_total'],
            'total' => $summary['total'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(Company $company, ?Invoice $invoice): array
    {
        return [
            'invoice' => $invoice === null ? null : $this->present($invoice),
            'registrationNumber' => $company->invoice_registration_number,
            'companyName' => $company->name,
        ];
    }

    private function findInvoice(Company $company, Invoice $invoice): Invoice
    {
        if ($invoice->company_id !== $company->id) {
            abort(404);
        }

        return $invoice->load('lines');
    }
}
