<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Company;
use App\Models\DescriptionRule;
use App\Models\User;
use App\Services\DescriptionRuleMatcher;
use Database\Seeders\AccountSeeder;
use Database\Seeders\DescriptionRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DescriptionRuleMatcherTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private DescriptionRuleMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);

        $user = User::factory()->create();
        $this->company = Company::create(['user_id' => $user->id]);
        DescriptionRuleSeeder::seedForCompany($this->company);

        $this->matcher = app(DescriptionRuleMatcher::class);
    }

    public function test_suggest_account_matches_seeded_keyword_in_description(): void
    {
        $account = $this->matcher->suggestAccount($this->company, 'Amazon.co.jp');

        $this->assertNotNull($account);
        $this->assertEquals('消耗品費', $account->name);
    }

    public function test_longest_keyword_wins_when_multiple_rules_match(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['user_id' => $user->id]);

        $shorterAccount = Account::where('name', '消耗品費')->firstOrFail();
        $longerAccount = Account::where('name', '通信費')->firstOrFail();

        DescriptionRule::create([
            'company_id' => $company->id,
            'keyword' => 'PayPay',
            'account_id' => $shorterAccount->id,
            'priority' => 6,
        ]);

        DescriptionRule::create([
            'company_id' => $company->id,
            'keyword' => 'PayPayカード',
            'account_id' => $longerAccount->id,
            'priority' => 12,
        ]);

        $account = $this->matcher->suggestAccount($company, 'PayPayカード 決済');

        $this->assertNotNull($account);
        $this->assertEquals('通信費', $account->name);
    }

    public function test_learn_from_confirmation_creates_new_rule_from_description(): void
    {
        $account = Account::where('name', '外注費')->firstOrFail();

        $this->matcher->learnFromConfirmation($this->company, 'AcmeVendor invoice payment', $account->id);

        $this->assertDatabaseHas('description_rules', [
            'company_id' => $this->company->id,
            'keyword' => 'AcmeVendor',
            'account_id' => $account->id,
        ]);

        $suggested = $this->matcher->suggestAccount($this->company, 'AcmeVendor invoice payment');
        $this->assertNotNull($suggested);
        $this->assertEquals($account->id, $suggested->id);
    }

    public function test_learn_from_confirmation_updates_existing_matching_rule(): void
    {
        $originalAccount = Account::where('name', '消耗品費')->firstOrFail();
        $newAccount = Account::where('name', '通信費')->firstOrFail();

        $this->assertNotNull($this->matcher->suggestAccount($this->company, 'Amazon.co.jp'));
        $this->assertEquals($originalAccount->id, $this->matcher->suggestAccount($this->company, 'Amazon.co.jp')->id);

        $this->matcher->learnFromConfirmation($this->company, 'Amazon.co.jp', $newAccount->id);

        $suggested = $this->matcher->suggestAccount($this->company, 'Amazon.co.jp');
        $this->assertNotNull($suggested);
        $this->assertEquals($newAccount->id, $suggested->id);
    }
}
