<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\QualifiedInvoiceDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create([
            'user_id' => $this->user->id,
            'name' => 'テスト株式会社',
            'address' => '東京都千代田区1-1',
            'representative_name' => '山田太郎',
            'invoice_registration_number' => 'T1234567890123',
        ]);
    }

    public function test_guest_cannot_open_invoices(): void
    {
        $this->get(route('invoices.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_create_invoice_without_posting_a_journal(): void
    {
        $this->actingAs($this->user)
            ->post(route('invoices.store'), $this->payload('2026-04-01'))
            ->assertRedirect();

        $invoice = Invoice::query()->where('company_id', $this->company->id)->first();
        $this->assertNotNull($invoice);

        $this->actingAs($this->user)
            ->get(route('invoices.edit', $invoice))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('invoices/form')
                ->where('registrationNumber', 'T1234567890123')
                ->where('invoice.invoice_number', '20260001')
                ->where('invoice.lines.0.transaction_date', '2026-03-15')
            );

        $this->assertSame('20260001', $invoice->invoice_number);
        $this->assertSame('株式会社サンプル', $invoice->customer_name);
        $this->assertDatabaseCount('journal_entries', 0);
        $consulting = $invoice->lines()->where('description', 'コンサルティング')->first();
        $this->assertNotNull($consulting);
        $this->assertSame('2026-03-15', $consulting->transaction_date->toDateString());
        $this->assertSame('10', $consulting->tax_rate->value);
        $this->assertSame(1005, $consulting->unit_price);
        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $invoice->id,
            'description' => '食品',
            'tax_rate' => '8',
        ]);
    }

    public function test_invoice_numbers_restart_each_year_and_stay_on_update(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->payload('2025-12-31'));
        $this->actingAs($this->user)->post(route('invoices.store'), $this->payload('2026-01-02'));
        $this->actingAs($this->user)->post(route('invoices.store'), $this->payload('2026-06-01'));

        $numbers = Invoice::query()->where('company_id', $this->company->id)->orderBy('invoice_number')->pluck('invoice_number');
        $this->assertSame(['20250001', '20260001', '20260002'], $numbers->all());

        $invoice = Invoice::query()->where('invoice_number', '20260001')->firstOrFail();

        $this->actingAs($this->user)
            ->put(route('invoices.update', $invoice), $this->payload('2024-01-01', '変更後株式会社'))
            ->assertRedirect();

        $this->assertSame('20260001', $invoice->fresh()->invoice_number);
        $this->assertSame('変更後株式会社', $invoice->fresh()->customer_name);
    }

    public function test_index_shows_registration_number_and_tax_breakdown(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->payload('2026-04-01'));

        $this->actingAs($this->user)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('invoices/index')
                ->where('registrationNumber', 'T1234567890123')
                ->has('invoices', 1)
                ->where('invoices.0.invoice_number', '20260001')
                ->where('invoices.0.rates.0.rate', '10')
                ->where('invoices.0.rates.0.base', 2010)
                ->where('invoices.0.rates.0.tax', 201)
                ->where('invoices.0.rates.1.rate', '8')
                ->where('invoices.0.rates.1.tax', 160)
                ->where('invoices.0.total', 4371)
            );
    }

    public function test_qualified_invoice_document_lists_registration_number_rate_and_tax(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->payload('2026-04-01'));
        $invoice = Invoice::query()->firstOrFail();

        $html = view('invoices.qualified', app(QualifiedInvoiceDocument::class)->data($invoice, $this->company))->render();

        $this->assertStringContainsString('登録番号', $html);
        $this->assertStringContainsString('T1234567890123', $html);
        $this->assertStringContainsString('テスト株式会社', $html);
        $this->assertStringContainsString('株式会社サンプル', $html);
        $this->assertStringContainsString('取引日', $html);
        $this->assertStringContainsString('2026年3月15日', $html);
        $this->assertStringContainsString('2026年4月1日', $html);
        $this->assertStringContainsString('10%', $html);
        $this->assertStringContainsString('8% 軽', $html);
        $this->assertStringContainsString('8%（軽減）', $html);
        $this->assertStringContainsString('消費税額', $html);
        $this->assertStringContainsString('1,005', $html);
        $this->assertStringContainsString('2,000', $html);
        $this->assertStringContainsString('201', $html);
        $this->assertStringContainsString('160', $html);
    }

    public function test_pdf_downloads_when_registration_number_is_set(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->payload('2026-04-01'));
        $invoice = Invoice::query()->firstOrFail();

        $response = $this->actingAs($this->user)->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_pdf_redirects_to_settings_when_registration_number_is_missing(): void
    {
        $this->company->update(['invoice_registration_number' => null]);
        $this->actingAs($this->user)->post(route('invoices.store'), $this->payload('2026-04-01'));
        $invoice = Invoice::query()->firstOrFail();

        $this->actingAs($this->user)
            ->get(route('invoices.pdf', $invoice))
            ->assertRedirect(route('fiscal-year.edit'))
            ->assertSessionHasErrors('invoice_registration_number');
    }

    public function test_other_company_cannot_open_invoice(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->payload('2026-04-01'));
        $invoice = Invoice::query()->firstOrFail();

        $other = User::factory()->create();
        Company::create(['user_id' => $other->id]);

        $this->actingAs($other)->get(route('invoices.edit', $invoice))->assertNotFound();
        $this->actingAs($other)->get(route('invoices.pdf', $invoice))->assertNotFound();
    }

    public function test_user_can_delete_invoice(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->payload('2026-04-01'));
        $invoice = Invoice::query()->firstOrFail();

        $this->actingAs($this->user)
            ->delete(route('invoices.destroy', $invoice))
            ->assertRedirect(route('invoices.index'));

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('invoice_lines', ['invoice_id' => $invoice->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $issueDate, string $customerName = '株式会社サンプル'): array
    {
        return [
            'issue_date' => $issueDate,
            'customer_name' => $customerName,
            'notes' => 'お振込期限は発行日の月末です。',
            'lines' => [
                [
                    'transaction_date' => '2026-03-15',
                    'description' => 'コンサルティング',
                    'quantity' => 1,
                    'unit_price' => 1005,
                    'tax_rate' => '10',
                ],
                [
                    'transaction_date' => $issueDate,
                    'description' => '追加作業',
                    'quantity' => 1,
                    'unit_price' => 1005,
                    'tax_rate' => '10',
                ],
                [
                    'transaction_date' => $issueDate,
                    'description' => '食品',
                    'quantity' => 2,
                    'unit_price' => 1000,
                    'tax_rate' => '8',
                ],
            ],
        ];
    }
}
