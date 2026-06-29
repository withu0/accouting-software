<?php

namespace Tests\Feature;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private FiscalYear $fiscalYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);

        $this->user = User::factory()->create();
        $this->company = Company::create(['user_id' => $this->user->id, 'name' => 'テスト株式会社']);

        $this->fiscalYear = FiscalYear::create([
            'company_id' => $this->company->id,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_active' => true,
        ]);

        $deposit = Account::findByName('預金');
        $revenue = Account::findByName('売上高');

        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'entry_date' => '2025-04-01',
            'description' => '売上',
            'source' => JournalSource::BankCsv,
        ]);
        $entry->lines()->createMany([
            ['account_id' => $deposit->id, 'debit' => 50000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 50000],
        ]);
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('reports'))->assertRedirect(route('login'));
    }

    public function test_index_returns_reports_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('reports'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/index')
                ->where('hasActiveFiscalYear', true)
                ->where('companyName', 'テスト株式会社')
            );
    }

    public function test_csv_export_returns_csv_response(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('reports.export', ['type' => 'pl', 'format' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }

    public function test_pdf_export_returns_pdf_response(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('reports.export', ['type' => 'pl', 'format' => 'pdf']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
    }

    #[DataProvider('reportTypeProvider')]
    public function test_pdf_export_returns_pdf_for_all_report_types(string $type): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('reports.export', ['type' => $type, 'format' => 'pdf']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function reportTypeProvider(): array
    {
        return [
            'pl' => ['pl'],
            'bs' => ['bs'],
            'journal' => ['journal'],
            'ledger' => ['ledger'],
        ];
    }

    public function test_consumption_tax_csv_export_returns_csv_response(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('reports.export', ['type' => 'consumption_tax', 'format' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('税区分', $response->streamedContent());
    }

    public function test_consumption_tax_pdf_export_returns_404(): void
    {
        $this->actingAs($this->user)
            ->get(route('reports.export', ['type' => 'consumption_tax', 'format' => 'pdf']))
            ->assertNotFound();
    }

    public function test_invalid_report_type_returns_404(): void
    {
        $this->actingAs($this->user)
            ->get(route('reports.export', ['type' => 'invalid', 'format' => 'csv']))
            ->assertNotFound();
    }
}
