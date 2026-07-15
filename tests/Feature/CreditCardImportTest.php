<?php

namespace Tests\Feature;

use App\Enums\CreditCardImportRowStatus;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Company;
use App\Models\CreditCardImportRow;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ConsumptionTaxPayload;
use Tests\TestCase;

class CreditCardImportTest extends TestCase
{
    use ConsumptionTaxPayload;
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
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_active' => true,
        ]);
    }

    public function test_upload_csv_shows_review_page(): void
    {
        $file = $this->makeSaisonCsvFile();

        $response = $this->actingAs($this->user)
            ->post(route('credit-card-import.store'), ['file' => $file]);

        $response->assertRedirect();
        $this->assertDatabaseCount('credit_card_import_rows', 15);

        preg_match('/credit-card-import\/(\d+)\/review/', $response->headers->get('Location'), $matches);
        $importId = (int) $matches[1];

        $this->actingAs($this->user)
            ->get(route('credit-card-import.review', $importId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('credit-card-import/review')
                ->has('rows', 15)
            );
    }

    public function test_confirm_creates_expense_and_payable_journal(): void
    {
        $file = $this->makeSaisonCsvFile();
        $this->actingAs($this->user)->post(route('credit-card-import.store'), ['file' => $file]);

        $row = CreditCardImportRow::where('description', '株式会社クラウドワークス')->firstOrFail();
        $expenseAccount = Account::where('name', '外注費')->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('credit-card-import.confirm', $row->credit_card_import_id), [
                'rows' => [['row_id' => $row->id, 'account_id' => $expenseAccount->id]],
            ])
            ->assertRedirect();

        $payableAccount = Account::where('name', '未払金')->firstOrFail();
        $inputTaxAccount = Account::where('name', '仮払消費税')->firstOrFail();

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $this->company->id,
            'description' => '株式会社クラウドワークス',
            'source' => JournalSource::CreditCardCsv->value,
        ]);

        $entry = $this->company->journalEntries()->where('description', '株式会社クラウドワークス')->first();
        $this->assertNotNull($entry);
        $this->assertCount(3, $entry->lines);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $expenseAccount->id,
            'debit' => 1815,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $inputTaxAccount->id,
            'debit' => 181,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $payableAccount->id,
            'debit' => 0,
            'credit' => 1996,
        ]);

        $row->refresh();
        $this->assertEquals(CreditCardImportRowStatus::Confirmed, $row->status);
    }

    public function test_reimport_creates_all_rows_again(): void
    {
        $file = $this->makeSaisonCsvFile();
        $firstResponse = $this->actingAs($this->user)->post(route('credit-card-import.store'), ['file' => $file]);

        preg_match('/credit-card-import\/(\d+)\/review/', $firstResponse->headers->get('Location'), $matches);
        $firstImportId = (int) $matches[1];

        $row = CreditCardImportRow::where('description', '株式会社クラウドワークス')->firstOrFail();
        $expenseAccount = Account::where('name', '外注費')->firstOrFail();
        $this->actingAs($this->user)
            ->post(route('credit-card-import.confirm', $row->credit_card_import_id), [
                'rows' => [['row_id' => $row->id, 'account_id' => $expenseAccount->id]],
            ]);

        $this->assertDatabaseCount('journal_entries', 1);

        $file2 = $this->makeSaisonCsvFile();
        $response = $this->actingAs($this->user)
            ->post(route('credit-card-import.store'), ['file' => $file2]);

        preg_match('/credit-card-import\/(\d+)\/review/', $response->headers->get('Location'), $matches);
        $secondImportId = (int) $matches[1];

        $this->assertNotSame($firstImportId, $secondImportId);
        $response->assertSessionHas('importSummary', fn ($summary) => $summary['new'] === 15 && $summary['duplicates'] === 0);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertEquals(15, CreditCardImportRow::where('credit_card_import_id', $secondImportId)->where('status', CreditCardImportRowStatus::Pending)->count());
    }

    public function test_skip_row(): void
    {
        $file = $this->makeSaisonCsvFile();
        $this->actingAs($this->user)->post(route('credit-card-import.store'), ['file' => $file]);

        $row = CreditCardImportRow::firstOrFail();

        $this->actingAs($this->user)
            ->post(route('credit-card-import.rows.skip', $row->id))
            ->assertRedirect();

        $row->refresh();
        $this->assertEquals(CreditCardImportRowStatus::Skipped, $row->status);
    }

    public function test_csv_outside_fiscal_year_is_rejected(): void
    {
        $this->company->activeFiscalYear()?->update([
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('credit-card-import.store'), ['file' => $this->makeSaisonCsvFile()]);

        $response->assertRedirect(route('credit-card-import'));
        $response->assertSessionHasErrors('file');
    }

    public function test_confirm_without_expense_account_shows_friendly_error(): void
    {
        $file = $this->makeSaisonCsvFile();
        $this->actingAs($this->user)->post(route('credit-card-import.store'), ['file' => $file]);

        $row = CreditCardImportRow::firstOrFail();

        $response = $this->actingAs($this->user)
            ->post(route('credit-card-import.confirm', $row->credit_card_import_id), [
                'rows' => [['row_id' => $row->id]],
            ]);

        $response->assertSessionHasErrors('rows');
        $response->assertSessionHasErrors([
            'rows' => '経費科目が未選択の取引があります。すべての取引に経費科目を選択してください。',
        ]);
    }

    public function test_delete_posted_credit_card_journal_removes_entry_and_import_row(): void
    {
        $file = $this->makeSaisonCsvFile();
        $this->actingAs($this->user)->post(route('credit-card-import.store'), ['file' => $file]);

        $row = CreditCardImportRow::where('description', '株式会社クラウドワークス')->firstOrFail();
        $expenseAccount = Account::where('name', '外注費')->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('credit-card-import.confirm', $row->credit_card_import_id), [
                'rows' => [['row_id' => $row->id, 'account_id' => $expenseAccount->id]],
            ]);

        $entry = JournalEntry::where('description', '株式会社クラウドワークス')->firstOrFail();
        $entryId = $entry->id;
        $rowId = $row->id;

        $this->actingAs($this->user)
            ->delete(route('journals.destroy', $entry))
            ->assertRedirect()
            ->assertSessionHas('success', 'クレジットカードCSV取込の仕訳を削除しました。');

        $this->assertDatabaseMissing('journal_entries', ['id' => $entryId]);
        $this->assertDatabaseMissing('journal_lines', ['journal_entry_id' => $entryId]);
        $this->assertDatabaseMissing('credit_card_import_rows', ['id' => $rowId]);
    }

    public function test_same_csv_row_can_be_reimported_after_journal_delete(): void
    {
        $file = $this->makeSaisonCsvFile();
        $this->actingAs($this->user)->post(route('credit-card-import.store'), ['file' => $file]);

        $row = CreditCardImportRow::where('description', '株式会社クラウドワークス')->firstOrFail();
        $expenseAccount = Account::where('name', '外注費')->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('credit-card-import.confirm', $row->credit_card_import_id), [
                'rows' => [['row_id' => $row->id, 'account_id' => $expenseAccount->id]],
            ]);

        $entry = JournalEntry::where('description', '株式会社クラウドワークス')->firstOrFail();
        $this->actingAs($this->user)->delete(route('journals.destroy', $entry));

        $response = $this->actingAs($this->user)
            ->post(route('credit-card-import.store'), ['file' => $this->makeSaisonCsvFile()]);

        $response->assertRedirect();
        $response->assertSessionHas('importSummary', fn ($summary) => $summary['new'] === 15 && $summary['duplicates'] === 0);
        $this->assertDatabaseHas('credit_card_import_rows', [
            'description' => '株式会社クラウドワークス',
            'status' => CreditCardImportRowStatus::Pending->value,
        ]);
    }

    public function test_index_shows_latest_five_imports(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->actingAs($this->user)->post(route('credit-card-import.store'), [
                'file' => $this->makeSaisonCsvFile(),
            ]);
        }

        $this->actingAs($this->user)
            ->get(route('credit-card-import'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('credit-card-import/index')
                ->has('recentImports', 5)
            );
    }

    private function makeSaisonCsvFile(): UploadedFile
    {
        $content = file_get_contents(base_path('samples/credit-card-sample/SAISON_2607.csv'));

        return UploadedFile::fake()->createWithContent('SAISON_2607.csv', $content);
    }
}
