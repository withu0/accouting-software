<?php

namespace Tests\Feature;

use App\Enums\BankImportRowStatus;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\BankImportRow;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\AccountSeeder;
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
        $firstResponse = $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        preg_match('/bank-import\/(\d+)\/review/', $firstResponse->headers->get('Location'), $matches);
        $bankImportId = (int) $matches[1];

        $row = BankImportRow::where('description', '振込 カ）ABC商事')->firstOrFail();
        $this->actingAs($this->user)
            ->post(route('bank-import.confirm', $row->bank_import_id), [
                'rows' => [['row_id' => $row->id]],
            ]);

        $this->assertDatabaseCount('journal_entries', 1);

        $file2 = $this->makeCsvFile();
        $response = $this->actingAs($this->user)
            ->post(route('bank-import.store'), ['file' => $file2]);

        $response->assertRedirect(route('bank-import.review', $bankImportId));
        $response->assertSessionHas('importSummary', fn ($summary) => $summary['duplicates'] === 3);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertEquals(2, BankImportRow::where('bank_import_id', $bankImportId)->where('status', BankImportRowStatus::Pending)->count());
    }

    public function test_withdrawal_row_has_no_suggested_account_after_upload(): void
    {
        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', 'Amazon.co.jp')->firstOrFail();

        $this->assertNull($row->suggested_account_id);
    }

    public function test_sample_csv_files_can_be_imported(): void
    {
        foreach (['bank-import-2025-04.csv', 'bank-import-2025-05.csv'] as $filename) {
            $file = $this->makeSampleCsvFile($filename);

            $response = $this->actingAs($this->user)
                ->post(route('bank-import.store'), ['file' => $file]);

            $response->assertRedirect();
            $response->assertSessionHas('importSummary', fn ($summary) => $summary['new'] > 0);
        }
    }

    public function test_reimporting_same_sample_csv_reports_all_duplicates(): void
    {
        $file = $this->makeSampleCsvFile('bank-import-2025-04.csv');
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $import = $this->company->bankImports()->firstOrFail();
        $rows = BankImportRow::where('bank_import_id', $import->id)->get();

        foreach ($rows as $row) {
            $payload = ['rows' => [['row_id' => $row->id]]];
            if ($row->withdrawal_amount > 0) {
                $payload['rows'][0]['account_id'] = Account::where('name', '消耗品費')->firstOrFail()->id;
            }

            $this->actingAs($this->user)->post(route('bank-import.confirm', $import->id), $payload);
        }

        $response = $this->actingAs($this->user)
            ->post(route('bank-import.store'), ['file' => $this->makeSampleCsvFile('bank-import-2025-04.csv')]);

        $response->assertRedirect(route('bank-import'));
        $response->assertSessionHasErrors('file');
    }

    public function test_reimporting_pending_sample_csv_resumes_review(): void
    {
        $file = $this->makeSampleCsvFile('bank-import-2025-04.csv');
        $firstResponse = $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);
        $firstResponse->assertRedirect();

        preg_match('/bank-import\/(\d+)\/review/', $firstResponse->headers->get('Location'), $matches);
        $bankImportId = (int) $matches[1];

        $response = $this->actingAs($this->user)
            ->post(route('bank-import.store'), ['file' => $this->makeSampleCsvFile('bank-import-2025-04.csv')]);

        $response->assertRedirect(route('bank-import.review', $bankImportId));
        $response->assertSessionHas('success', '未完了の取込があります。記帳を続けてください。');
    }

    public function test_sample_csv_outside_fiscal_year_is_rejected(): void
    {
        $this->company->activeFiscalYear()?->update([
            'start_date' => '2025-05-01',
            'end_date' => '2026-04-30',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('bank-import.store'), ['file' => $this->makeSampleCsvFile('bank-import-2025-04.csv')]);

        $response->assertRedirect(route('bank-import'));
        $response->assertSessionHasErrors('file');
    }

    public function test_delete_posted_csv_journal_removes_entry_lines_and_import_row(): void
    {
        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', '振込 カ）ABC商事')->firstOrFail();
        $this->actingAs($this->user)
            ->post(route('bank-import.confirm', $row->bank_import_id), [
                'rows' => [['row_id' => $row->id]],
            ]);

        $entry = JournalEntry::where('description', '振込 カ）ABC商事')->firstOrFail();
        $entryId = $entry->id;
        $rowId = $row->id;

        $this->actingAs($this->user)
            ->delete(route('journals.destroy', $entry))
            ->assertRedirect()
            ->assertSessionHas('success', '銀行CSV取込の仕訳を削除しました。');

        $this->assertDatabaseMissing('journal_entries', ['id' => $entryId]);
        $this->assertDatabaseMissing('journal_lines', ['journal_entry_id' => $entryId]);
        $this->assertDatabaseMissing('bank_import_rows', ['id' => $rowId]);
    }

    public function test_same_csv_row_can_be_reimported_after_journal_delete(): void
    {
        $file = $this->makeCsvFile();
        $firstResponse = $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        preg_match('/bank-import\/(\d+)\/review/', $firstResponse->headers->get('Location'), $matches);
        $bankImportId = (int) $matches[1];

        $row = BankImportRow::where('description', '振込 カ）ABC商事')->firstOrFail();
        $this->actingAs($this->user)
            ->post(route('bank-import.confirm', $row->bank_import_id), [
                'rows' => [['row_id' => $row->id]],
            ]);

        $entry = JournalEntry::where('description', '振込 カ）ABC商事')->firstOrFail();
        $this->actingAs($this->user)->delete(route('journals.destroy', $entry));

        $response = $this->actingAs($this->user)
            ->post(route('bank-import.store'), ['file' => $this->makeCsvFile()]);

        $response->assertRedirect();
        $response->assertSessionHas('importSummary', fn ($summary) => $summary['new'] === 1 && $summary['duplicates'] === 2);
        $this->assertDatabaseHas('bank_import_rows', [
            'description' => '振込 カ）ABC商事',
            'status' => BankImportRowStatus::Pending->value,
        ]);
        $this->assertEquals(1, BankImportRow::where('description', '振込 カ）ABC商事')->count());
    }

    public function test_cannot_delete_non_bank_csv_journal_from_journals_route(): void
    {
        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->company->activeFiscalYear()->id,
            'entry_date' => '2025-05-01',
            'description' => '立替経費',
            'source' => JournalSource::AdvanceExpense,
        ]);

        $this->actingAs($this->user)
            ->delete(route('journals.destroy', $entry))
            ->assertNotFound();

        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id]);
    }

    public function test_cannot_delete_other_company_bank_csv_journal(): void
    {
        $otherUser = User::factory()->create();
        $otherCompany = Company::create(['user_id' => $otherUser->id]);
        FiscalYear::create([
            'company_id' => $otherCompany->id,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_active' => true,
        ]);

        $entry = JournalEntry::create([
            'company_id' => $otherCompany->id,
            'fiscal_year_id' => $otherCompany->activeFiscalYear()->id,
            'entry_date' => '2025-05-01',
            'description' => '他社のCSV仕訳',
            'source' => JournalSource::BankCsv,
        ]);

        $this->actingAs($this->user)
            ->delete(route('journals.destroy', $entry))
            ->assertNotFound();

        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id]);
    }

    private function makeSampleCsvFile(string $filename): UploadedFile
    {
        $content = file_get_contents(base_path("samples/bank-csv/{$filename}"));

        return UploadedFile::fake()->createWithContent($filename, $content);
    }

    private function makeCsvFile(): UploadedFile
    {
        $content = file_get_contents(base_path('tests/fixtures/mock-bank.csv'));

        return UploadedFile::fake()->createWithContent('mock-bank.csv', $content);
    }
}
