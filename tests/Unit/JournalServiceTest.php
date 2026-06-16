<?php

namespace Tests\Unit;

use App\Enums\JournalSource;
use App\Exceptions\UnbalancedJournalException;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\JournalService;
use Carbon\Carbon;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class JournalServiceTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journalService;

    private Company $company;

    private Account $depositAccount;

    private Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);
        $this->journalService = new JournalService;

        $user = User::factory()->create();
        $this->company = Company::create(['user_id' => $user->id]);
        FiscalYear::create([
            'company_id' => $this->company->id,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_active' => true,
        ]);

        $this->depositAccount = Account::where('name', '預金')->firstOrFail();
        $this->revenueAccount = Account::where('name', '売上高')->firstOrFail();
    }

    public function test_balanced_entry_creates_journal_entries_and_lines(): void
    {
        $entry = $this->journalService->createBalancedEntry(
            $this->company,
            Carbon::parse('2025-05-01'),
            '売上入金',
            JournalSource::Manual,
            [
                ['account_id' => $this->depositAccount->id, 'debit' => 100000, 'credit' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 100000],
            ],
        );

        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'company_id' => $this->company->id,
            'description' => '売上入金',
            'source' => JournalSource::Manual->value,
        ]);

        $this->assertCount(2, $entry->lines);
        $this->assertEquals(100000, $entry->lines->sum('debit'));
        $this->assertEquals(100000, $entry->lines->sum('credit'));
    }

    public function test_unbalanced_entry_throws_exception(): void
    {
        $this->expectException(UnbalancedJournalException::class);

        $this->journalService->createBalancedEntry(
            $this->company,
            Carbon::parse('2025-05-01'),
            '不正仕訳',
            JournalSource::Manual,
            [
                ['account_id' => $this->depositAccount->id, 'debit' => 100000, 'credit' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 50000],
            ],
        );
    }

    public function test_entry_outside_fiscal_year_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('outside the active fiscal year');

        $this->journalService->createBalancedEntry(
            $this->company,
            Carbon::parse('2026-04-01'),
            '期間外',
            JournalSource::Manual,
            [
                ['account_id' => $this->depositAccount->id, 'debit' => 10000, 'credit' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 10000],
            ],
        );
    }

    public function test_duplicate_idempotency_key_throws_exception(): void
    {
        $lines = [
            ['account_id' => $this->depositAccount->id, 'debit' => 10000, 'credit' => 0],
            ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 10000],
        ];

        $this->journalService->createBalancedEntry(
            $this->company,
            Carbon::parse('2025-05-01'),
            '重複テスト',
            JournalSource::BankCsv,
            $lines,
            'import-row-1',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('idempotency key');

        $this->journalService->createBalancedEntry(
            $this->company,
            Carbon::parse('2025-05-02'),
            '重複テスト2',
            JournalSource::BankCsv,
            $lines,
            'import-row-1',
        );
    }

    public function test_invalid_account_id_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid');

        $this->journalService->createBalancedEntry(
            $this->company,
            Carbon::parse('2025-05-01'),
            '不正科目',
            JournalSource::Manual,
            [
                ['account_id' => 99999, 'debit' => 10000, 'credit' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 10000],
            ],
        );
    }

    public function test_validate_lines_detects_xor_violation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->journalService->validateLines([
            ['account_id' => 1, 'debit' => 1000, 'credit' => 1000],
            ['account_id' => 2, 'debit' => 0, 'credit' => 0],
        ]);
    }
}
