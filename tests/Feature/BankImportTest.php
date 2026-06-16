<?php

namespace Tests\Feature;

use App\Enums\BankImportRowStatus;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\BankImportRow;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\DescriptionRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BankImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);

        $this->user = User::factory()->create();
        $this->company = Company::create(['user_id' => $this->user->id]);
        DescriptionRuleSeeder::seedForCompany($this->company);

        FiscalYear::create([
            'company_id' => $this->company->id,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_active' => true,
        ]);
    }

    public function test_upload_csv_shows_review_page(): void
    {
        $file = $this->makeCsvFile();

        $response = $this->actingAs($this->user)
            ->post(route('bank-import.store'), ['file' => $file]);

        $response->assertRedirect();
        $this->assertDatabaseCount('bank_import_rows', 3);

        $importId = $response->headers->get('Location');
        preg_match('/bank-import\/(\d+)\/review/', $importId, $matches);
        $bankImportId = (int) $matches[1];

        $this->actingAs($this->user)
            ->get(route('bank-import.review', $bankImportId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bank-import/review')
                ->has('rows', 3)
            );
    }

    public function test_confirm_deposit_creates_revenue_journal(): void
    {
        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', '振込 カ）ABC商事')->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('bank-import.confirm', $row->bank_import_id), [
                'rows' => [['row_id' => $row->id]],
            ])
            ->assertRedirect();

        $depositAccount = Account::where('name', '預金')->firstOrFail();
        $revenueAccount = Account::where('name', '売上高')->firstOrFail();

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $this->company->id,
            'description' => '振込 カ）ABC商事',
            'source' => JournalSource::BankCsv->value,
        ]);

        $entry = $this->company->journalEntries()->where('description', '振込 カ）ABC商事')->first();
        $this->assertNotNull($entry);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $depositAccount->id,
            'debit' => 100000,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => 100000,
        ]);

        $row->refresh();
        $this->assertEquals(BankImportRowStatus::Confirmed, $row->status);
    }

    public function test_confirm_withdrawal_creates_expense_journal(): void
    {
        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', 'Amazon.co.jp')->firstOrFail();
        $expenseAccount = Account::where('name', '消耗品費')->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('bank-import.confirm', $row->bank_import_id), [
                'rows' => [['row_id' => $row->id, 'account_id' => $expenseAccount->id]],
            ])
            ->assertRedirect();

        $depositAccount = Account::where('name', '預金')->firstOrFail();

        $entry = $this->company->journalEntries()->where('description', 'Amazon.co.jp')->first();
        $this->assertNotNull($entry);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $expenseAccount->id,
            'debit' => 5000,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $depositAccount->id,
            'debit' => 0,
            'credit' => 5000,
        ]);
    }

    public function test_reimport_skips_duplicate_rows(): void
    {
        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', '振込 カ）ABC商事')->firstOrFail();
        $this->actingAs($this->user)
            ->post(route('bank-import.confirm', $row->bank_import_id), [
                'rows' => [['row_id' => $row->id]],
            ]);

        $this->assertDatabaseCount('journal_entries', 1);

        $file2 = $this->makeCsvFile();
        $response = $this->actingAs($this->user)
            ->post(route('bank-import.store'), ['file' => $file2]);

        $response->assertRedirect();
        $response->assertSessionHas('importSummary', fn ($summary) => $summary['duplicates'] === 3);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_learned_rule_suggests_account_on_second_import(): void
    {
        $csv1 = <<<'CSV'
日付,摘要,入金額,出金額,残高
2025-04-10,新規店舗 XYZ商店,,8000,100000
CSV;

        $file1 = UploadedFile::fake()->createWithContent('bank1.csv', $csv1);
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file1]);

        $row = BankImportRow::where('description', '新規店舗 XYZ商店')->firstOrFail();
        $expenseAccount = Account::where('name', '会議費')->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('bank-import.confirm', $row->bank_import_id), [
                'rows' => [['row_id' => $row->id, 'account_id' => $expenseAccount->id]],
            ]);

        $csv2 = <<<'CSV'
日付,摘要,入金額,出金額,残高
2025-04-11,新規店舗 XYZ商店 2回目,,3000,97000
CSV;

        $file2 = UploadedFile::fake()->createWithContent('bank2.csv', $csv2);
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file2]);

        $row2 = BankImportRow::where('description', '新規店舗 XYZ商店 2回目')->firstOrFail();
        $this->assertEquals($expenseAccount->id, $row2->suggested_account_id);
    }

    public function test_amazon_row_has_suggested_account_from_seeded_rules(): void
    {
        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', 'Amazon.co.jp')->firstOrFail();
        $expenseAccount = Account::where('name', '消耗品費')->firstOrFail();

        $this->assertEquals($expenseAccount->id, $row->suggested_account_id);
    }

    private function makeCsvFile(): UploadedFile
    {
        $content = file_get_contents(base_path('tests/fixtures/mock-bank.csv'));

        return UploadedFile::fake()->createWithContent('mock-bank.csv', $content);
    }
}
