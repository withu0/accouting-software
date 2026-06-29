<?php

namespace Tests\Feature;

use App\Enums\JournalSource;
use App\Http\Controllers\TransferJournalController;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ConsumptionTaxPayload;
use Tests\TestCase;

class TransferJournalTest extends TestCase
{
    use ConsumptionTaxPayload;
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private FiscalYear $activeFiscalYear;

    private Account $debitAccount;

    private Account $creditAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);

        $this->user = User::factory()->create();
        $this->company = Company::create(['user_id' => $this->user->id]);

        $this->activeFiscalYear = FiscalYear::create([
            'company_id' => $this->company->id,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_active' => true,
        ]);

        $this->debitAccount = Account::where('name', '売掛金')->firstOrFail();
        $this->creditAccount = Account::where('name', '売上高')->firstOrFail();
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('transfer-journal.index'))->assertRedirect(route('login'));
    }

    public function test_create_posts_balanced_transfer_journal(): void
    {
        $this->actingAs($this->user)
            ->post(route('transfer-journal.store'), array_merge([
                'entry_date' => '2025-05-15',
                'debit_account_id' => $this->debitAccount->id,
                'debit_amount' => 100000,
                'credit_account_id' => $this->creditAccount->id,
                'credit_amount' => 100000,
                'description' => '売掛金計上',
            ], $this->transferTaxPayload()))
            ->assertRedirect();

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $this->company->id,
            'description' => '売掛金計上',
            'source' => JournalSource::Transfer->value,
        ]);

        $entry = JournalEntry::where('description', '売掛金計上')->firstOrFail();
        $this->assertCount(2, $entry->lines);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $this->debitAccount->id,
            'debit' => 100000,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $this->creditAccount->id,
            'debit' => 0,
            'credit' => 100000,
        ]);
    }

    public function test_unbalanced_amounts_are_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('transfer-journal.store'), array_merge([
                'entry_date' => '2025-05-15',
                'debit_account_id' => $this->debitAccount->id,
                'debit_amount' => 100000,
                'credit_account_id' => $this->creditAccount->id,
                'credit_amount' => 50000,
                'description' => 'テスト',
            ], $this->transferTaxPayload()))
            ->assertSessionHasErrors('credit_amount');
    }

    public function test_same_debit_and_credit_account_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('transfer-journal.store'), array_merge([
                'entry_date' => '2025-05-15',
                'debit_account_id' => $this->debitAccount->id,
                'debit_amount' => 100000,
                'credit_account_id' => $this->debitAccount->id,
                'credit_amount' => 100000,
                'description' => 'テスト',
            ], $this->transferTaxPayload()))
            ->assertSessionHasErrors('credit_account_id');
    }

    public function test_date_outside_fiscal_year_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('transfer-journal.store'), array_merge([
                'entry_date' => '2027-01-01',
                'debit_account_id' => $this->debitAccount->id,
                'debit_amount' => 100000,
                'credit_account_id' => $this->creditAccount->id,
                'credit_amount' => 100000,
                'description' => 'テスト',
            ], $this->transferTaxPayload()))
            ->assertSessionHasErrors('entry_date');
    }

    public function test_list_shows_only_active_fiscal_year_transfers(): void
    {
        $activeEntry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->activeFiscalYear->id,
            'entry_date' => '2025-05-01',
            'description' => '当期の振替',
            'source' => JournalSource::Transfer,
        ]);
        $activeEntry->lines()->createMany([
            ['account_id' => $this->debitAccount->id, 'debit' => 10000, 'credit' => 0],
            ['account_id' => $this->creditAccount->id, 'debit' => 0, 'credit' => 10000],
        ]);

        $inactiveFiscalYear = FiscalYear::create([
            'company_id' => $this->company->id,
            'start_date' => '2024-04-01',
            'end_date' => '2025-03-31',
            'is_active' => false,
        ]);

        $inactiveEntry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $inactiveFiscalYear->id,
            'entry_date' => '2024-05-01',
            'description' => '前期の振替',
            'source' => JournalSource::Transfer,
        ]);
        $inactiveEntry->lines()->createMany([
            ['account_id' => $this->debitAccount->id, 'debit' => 5000, 'credit' => 0],
            ['account_id' => $this->creditAccount->id, 'debit' => 0, 'credit' => 5000],
        ]);

        $this->actingAs($this->user)
            ->get(route('transfer-journal.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('other/transfer-journal')
                ->has('entries', 1)
                ->where('entries.0.description', '当期の振替')
            );
    }

    public function test_delete_removes_entry_and_lines(): void
    {
        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->activeFiscalYear->id,
            'entry_date' => '2025-05-01',
            'description' => '削除対象',
            'source' => JournalSource::Transfer,
        ]);
        $entry->lines()->createMany([
            ['account_id' => $this->debitAccount->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->creditAccount->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $this->actingAs($this->user)
            ->delete(route('transfer-journal.destroy', $entry))
            ->assertRedirect();

        $this->assertDatabaseMissing('journal_entries', ['id' => $entry->id]);
        $this->assertDatabaseCount('journal_lines', 0);
    }

    public function test_delete_other_company_entry_returns_404(): void
    {
        $otherUser = User::factory()->create();
        $otherCompany = Company::create(['user_id' => $otherUser->id]);
        $otherFiscalYear = FiscalYear::create([
            'company_id' => $otherCompany->id,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_active' => true,
        ]);

        $entry = JournalEntry::create([
            'company_id' => $otherCompany->id,
            'fiscal_year_id' => $otherFiscalYear->id,
            'entry_date' => '2025-05-01',
            'description' => '他社の振替',
            'source' => JournalSource::Transfer,
        ]);
        $entry->lines()->createMany([
            ['account_id' => $this->debitAccount->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->creditAccount->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $this->actingAs($this->user)
            ->delete(route('transfer-journal.destroy', $entry))
            ->assertNotFound();
    }

    public function test_presets_resolve_to_valid_account_ids(): void
    {
        $presets = TransferJournalController::resolvePresets();

        $this->assertCount(7, $presets);

        foreach ($presets as $preset) {
            $this->assertNotEmpty($preset['id']);
            $this->assertNotEmpty($preset['label']);
            $this->assertDatabaseHas('accounts', ['id' => $preset['debit_account_id']]);
            $this->assertDatabaseHas('accounts', ['id' => $preset['credit_account_id']]);
            $this->assertNotEquals($preset['debit_account_id'], $preset['credit_account_id']);
        }
    }
}
