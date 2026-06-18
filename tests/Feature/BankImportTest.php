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

        FiscalYear::create([
            'company_id' => $this->company->id,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_active' => true,
        ]);

        DescriptionRuleSeeder::seedForCompany($this->company);
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
        $outputTaxAccount = Account::where('name', '仮受消費税')->firstOrFail();

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $this->company->id,
            'description' => '振込 カ）ABC商事',
            'source' => JournalSource::BankCsv->value,
        ]);

        $entry = $this->company->journalEntries()->where('description', '振込 カ）ABC商事')->first();
        $this->assertNotNull($entry);
        $this->assertCount(3, $entry->lines);
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
            'credit' => 90910,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $outputTaxAccount->id,
            'debit' => 0,
            'credit' => 9090,
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
        $inputTaxAccount = Account::where('name', '仮払消費税')->firstOrFail();

        $entry = $this->company->journalEntries()->where('description', 'Amazon.co.jp')->first();
        $this->assertNotNull($entry);
        $this->assertCount(3, $entry->lines);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $expenseAccount->id,
            'debit' => 4546,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $inputTaxAccount->id,
            'debit' => 454,
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

    public function test_withdrawal_row_gets_suggested_account_after_upload(): void
    {
        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', 'Amazon.co.jp')->firstOrFail();
        $expenseAccount = Account::where('name', '消耗品費')->firstOrFail();

        $this->assertEquals($expenseAccount->id, $row->suggested_account_id);

        $this->actingAs($this->user)
            ->get(route('bank-import.review', $row->bank_import_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bank-import/review')
                ->where('rows.1.description', 'Amazon.co.jp')
                ->where('rows.1.suggested_account_id', $expenseAccount->id)
            );
    }

    public function test_confirm_withdrawal_learns_rule_for_future_import(): void
    {
        $csv = <<<'CSV'
日付,摘要,入金額,出金額,残高
2025-04-10,AcmeVendor,,3000,1000000
CSV;

        $file = UploadedFile::fake()->createWithContent('learn-test.csv', $csv);
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', 'AcmeVendor')->firstOrFail();
        $this->assertNull($row->suggested_account_id);

        $expenseAccount = Account::where('name', '外注費')->firstOrFail();
        $this->actingAs($this->user)
            ->post(route('bank-import.confirm', $row->bank_import_id), [
                'rows' => [['row_id' => $row->id, 'account_id' => $expenseAccount->id]],
            ]);

        $this->assertDatabaseHas('description_rules', [
            'company_id' => $this->company->id,
            'keyword' => 'AcmeVendor',
            'account_id' => $expenseAccount->id,
        ]);

        $csv2 = <<<'CSV'
日付,摘要,入金額,出金額,残高
2025-04-11,AcmeVendor,,4500,995500
CSV;

        $file2 = UploadedFile::fake()->createWithContent('learn-test-2.csv', $csv2);
        $response = $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file2]);
        $response->assertRedirect();

        $newRow = BankImportRow::where('description', 'AcmeVendor')
            ->where('status', BankImportRowStatus::Pending)
            ->firstOrFail();

        $this->assertEquals($expenseAccount->id, $newRow->suggested_account_id);
    }

    public function test_user_can_override_suggested_account_and_rule_is_updated(): void
    {
        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', 'Amazon.co.jp')->firstOrFail();
        $overrideAccount = Account::where('name', '通信費')->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('bank-import.confirm', $row->bank_import_id), [
                'rows' => [['row_id' => $row->id, 'account_id' => $overrideAccount->id]],
            ]);

        $csv = <<<'CSV'
日付,摘要,入金額,出金額,残高
2025-04-15,Amazon.co.jp,,2000,1000000
CSV;

        $file2 = UploadedFile::fake()->createWithContent('override-test.csv', $csv);
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file2]);

        $newRow = BankImportRow::where('description', 'Amazon.co.jp')
            ->where('status', BankImportRowStatus::Pending)
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals($overrideAccount->id, $newRow->suggested_account_id);
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

    public function test_bulk_delete_posted_csv_journals(): void
    {
        $expenseAccount = Account::expenseAccounts()->firstOrFail();

        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $rows = BankImportRow::orderBy('id')->get();
        $this->actingAs($this->user)
            ->post(route('bank-import.confirm', $rows->first()->bank_import_id), [
                'rows' => $rows->map(fn ($row) => [
                    'row_id' => $row->id,
                    ...($row->withdrawal_amount > 0 ? ['account_id' => $expenseAccount->id] : []),
                ])->all(),
            ]);

        $entries = JournalEntry::where('source', JournalSource::BankCsv)->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(2, $entries->count());

        $idsToDelete = $entries->take(2)->pluck('id')->all();

        $this->actingAs($this->user)
            ->delete(route('journals.destroy-bulk'), ['ids' => $idsToDelete])
            ->assertRedirect()
            ->assertSessionHas('success', '2件の銀行CSV取込仕訳を削除しました。');

        foreach ($idsToDelete as $id) {
            $this->assertDatabaseMissing('journal_entries', ['id' => $id]);
            $this->assertDatabaseMissing('journal_lines', ['journal_entry_id' => $id]);
        }
    }

    public function test_bulk_delete_rejects_non_bank_csv_journal_ids(): void
    {
        $manualEntry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->company->activeFiscalYear()->id,
            'entry_date' => '2025-05-01',
            'description' => '手動仕訳',
            'source' => JournalSource::Manual,
        ]);

        $this->actingAs($this->user)
            ->delete(route('journals.destroy-bulk'), ['ids' => [$manualEntry->id]])
            ->assertNotFound();

        $this->assertDatabaseHas('journal_entries', ['id' => $manualEntry->id]);
    }

    public function test_bulk_delete_requires_at_least_one_id(): void
    {
        $this->actingAs($this->user)
            ->delete(route('journals.destroy-bulk'), ['ids' => []])
            ->assertSessionHasErrors('ids');
    }

    public function test_update_pending_row_on_review(): void
    {
        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', 'Amazon.co.jp')->firstOrFail();
        $expenseAccount = Account::where('name', '通信費')->firstOrFail();
        $originalHash = $row->row_hash;

        $this->actingAs($this->user)
            ->patch(route('bank-import.rows.update', $row), [
                'transaction_date' => '2025-04-10',
                'description' => 'Amazon.co.jp 修正',
                'amount' => 6000,
                'account_id' => $expenseAccount->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '取引を更新しました。');

        $row->refresh();
        $this->assertEquals('2025-04-10', $row->transaction_date->format('Y-m-d'));
        $this->assertEquals('Amazon.co.jp 修正', $row->description);
        $this->assertEquals(6000, $row->withdrawal_amount);
        $this->assertEquals(BankImportRowStatus::Pending, $row->status);
        $this->assertEquals($originalHash, $row->row_hash);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_update_posted_withdrawal_rebuilds_journal(): void
    {
        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', 'Amazon.co.jp')->firstOrFail();
        $originalHash = $row->row_hash;
        $expenseAccount = Account::where('name', '消耗品費')->firstOrFail();
        $newExpenseAccount = Account::where('name', '通信費')->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('bank-import.confirm', $row->bank_import_id), [
                'rows' => [['row_id' => $row->id, 'account_id' => $expenseAccount->id]],
            ]);

        $entry = JournalEntry::where('description', 'Amazon.co.jp')->firstOrFail();

        $this->actingAs($this->user)
            ->patch(route('journals.update', $entry), [
                'transaction_date' => '2025-04-05',
                'description' => 'Amazon.co.jp 更新',
                'amount' => 8000,
                'account_id' => $newExpenseAccount->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '仕訳を更新しました。');

        $row->refresh();
        $entry->refresh();

        $this->assertEquals($originalHash, $row->row_hash);
        $this->assertEquals('Amazon.co.jp 更新', $entry->description);
        $this->assertEquals('2025-04-05', $entry->entry_date->format('Y-m-d'));
        $this->assertEquals(8000, $row->withdrawal_amount);

        $depositAccount = Account::where('name', '預金')->firstOrFail();
        $inputTaxAccount = Account::where('name', '仮払消費税')->firstOrFail();

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $newExpenseAccount->id,
            'debit' => 7273,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $inputTaxAccount->id,
            'debit' => 727,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $depositAccount->id,
            'debit' => 0,
            'credit' => 8000,
        ]);
    }

    public function test_update_posted_deposit_with_new_revenue_account(): void
    {
        $otherRevenue = Account::create([
            'code' => '4102',
            'name' => '雑収入',
            'type' => \App\Enums\AccountType::Revenue,
            'display_order' => 22,
        ]);

        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', '振込 カ）ABC商事')->firstOrFail();
        $this->actingAs($this->user)
            ->post(route('bank-import.confirm', $row->bank_import_id), [
                'rows' => [['row_id' => $row->id]],
            ]);

        $entry = JournalEntry::where('description', '振込 カ）ABC商事')->firstOrFail();

        $this->actingAs($this->user)
            ->patch(route('bank-import.rows.update', $row), [
                'transaction_date' => '2025-04-02',
                'description' => '振込 カ）ABC商事 修正',
                'amount' => 110000,
                'account_id' => $otherRevenue->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '取引を更新しました。');

        $entry->refresh();
        $this->assertEquals('振込 カ）ABC商事 修正', $entry->description);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $otherRevenue->id,
            'debit' => 0,
            'credit' => 100000,
        ]);
    }

    public function test_update_row_rejects_date_outside_fiscal_year(): void
    {
        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', 'Amazon.co.jp')->firstOrFail();
        $expenseAccount = Account::where('name', '消耗品費')->firstOrFail();

        $this->actingAs($this->user)
            ->patch(route('bank-import.rows.update', $row), [
                'transaction_date' => '2024-01-01',
                'description' => $row->description,
                'amount' => 5000,
                'account_id' => $expenseAccount->id,
            ])
            ->assertSessionHasErrors('transaction_date');
    }

    public function test_update_deposit_can_use_any_account(): void
    {
        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', '振込 カ）ABC商事')->firstOrFail();
        $expenseAccount = Account::where('name', '消耗品費')->firstOrFail();

        $this->actingAs($this->user)
            ->patch(route('bank-import.rows.update', $row), [
                'transaction_date' => '2025-04-01',
                'description' => $row->description,
                'amount' => 100000,
                'account_id' => $expenseAccount->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '取引を更新しました。');

        $row->refresh();
        $this->assertEquals(BankImportRowStatus::Pending, $row->status);
    }

    public function test_cannot_update_non_bank_csv_journal(): void
    {
        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->company->activeFiscalYear()->id,
            'entry_date' => '2025-05-01',
            'description' => '立替経費',
            'source' => JournalSource::AdvanceExpense,
        ]);

        $expenseAccount = Account::expenseAccounts()->firstOrFail();

        $this->actingAs($this->user)
            ->patch(route('journals.update', $entry), [
                'transaction_date' => '2025-05-02',
                'description' => '更新',
                'amount' => 1000,
                'account_id' => $expenseAccount->id,
            ])
            ->assertNotFound();
    }

    public function test_history_index_lists_imports(): void
    {
        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $this->actingAs($this->user)
            ->get(route('bank-import.history'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bank-import/history')
                ->has('imports.data', 1)
                ->where('imports.data.0.row_count', 3)
                ->where('imports.data.0.pending_count', 3)
            );
    }

    public function test_show_page_lists_all_row_statuses(): void
    {
        $file = $this->makeCsvFile();
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $import = $this->company->bankImports()->firstOrFail();
        $rows = BankImportRow::where('bank_import_id', $import->id)->orderBy('id')->get();

        $depositRow = $rows->firstWhere('description', '振込 カ）ABC商事');
        $this->actingAs($this->user)
            ->post(route('bank-import.confirm', $import->id), [
                'rows' => [['row_id' => $depositRow->id]],
            ]);

        $this->actingAs($this->user)
            ->post(route('bank-import.rows.skip', $rows->last()->id));

        $this->actingAs($this->user)
            ->get(route('bank-import.show', $import))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bank-import/show')
                ->has('rows', 3)
                ->where('rows.0.status', BankImportRowStatus::Confirmed->value)
                ->where('rows.1.status', BankImportRowStatus::Pending->value)
                ->where('rows.2.status', BankImportRowStatus::Skipped->value)
            );
    }

    public function test_edit_withdrawal_learns_rule(): void
    {
        $csv = <<<'CSV'
日付,摘要,入金額,出金額,残高
2025-04-10,LearnEditVendor,,3000,1000000
CSV;

        $file = UploadedFile::fake()->createWithContent('learn-edit.csv', $csv);
        $this->actingAs($this->user)->post(route('bank-import.store'), ['file' => $file]);

        $row = BankImportRow::where('description', 'LearnEditVendor')->firstOrFail();
        $expenseAccount = Account::where('name', '外注費')->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('bank-import.confirm', $row->bank_import_id), [
                'rows' => [['row_id' => $row->id, 'account_id' => Account::where('name', '消耗品費')->firstOrFail()->id]],
            ]);

        $this->actingAs($this->user)
            ->patch(route('bank-import.rows.update', $row), [
                'transaction_date' => '2025-04-10',
                'description' => 'LearnEditVendor',
                'amount' => 3500,
                'account_id' => $expenseAccount->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('description_rules', [
            'company_id' => $this->company->id,
            'keyword' => 'LearnEditVendor',
            'account_id' => $expenseAccount->id,
        ]);
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

    /**
     * @return array<string, array{0: string, 1: int, 2: string}>
     */
    public static function bankSampleFileProvider(): array
    {
        return [
            'native april' => ['native-2025-04.csv', 5, 'native'],
            'gmo native april' => ['gmo-native-2025-04.csv', 5, 'gmo_native'],
            'rakuten april' => ['rakuten-2025-04.csv', 5, 'rakuten'],
            'sbi april' => ['sbi-2025-04.csv', 5, 'sbi_sumishin'],
            'gmo zengin csv april' => ['gmo-zengin-csv-2025-04.csv', 5, 'gmo_zengin_csv'],
            'gmo zengin fixed april' => ['gmo-zengin-fixed-2025-04.txt', 5, 'gmo_zengin_fixed'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bankSampleFileProvider')]
    public function test_uploads_multi_bank_sample_files(string $filename, int $expectedRows, string $expectedFormat): void
    {
        $content = file_get_contents(base_path("samples/bank-csv-samples/{$filename}"));
        $file = UploadedFile::fake()->createWithContent($filename, $content);

        $response = $this->actingAs($this->user)
            ->post(route('bank-import.store'), ['file' => $file]);

        $response->assertRedirect();
        $this->assertDatabaseCount('bank_import_rows', $expectedRows);
        $this->assertDatabaseHas('bank_imports', [
            'company_id' => $this->company->id,
            'detected_format' => $expectedFormat,
        ]);
    }
}
