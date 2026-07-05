<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReceiptScanTest extends TestCase
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

        Config::set('services.openai.key', 'test-openai-key');
    }

    public function test_requires_authentication(): void
    {
        $file = UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg');

        $this->post(route('receipt-scans.store'), ['file' => $file])
            ->assertRedirect(route('login'));
    }

    public function test_rejects_invalid_file_type(): void
    {
        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)
            ->from(route('advance-expenses'))
            ->post(route('receipt-scans.store'), ['file' => $file])
            ->assertRedirect(route('advance-expenses'))
            ->assertSessionHasErrors('file');
    }

    public function test_returns_error_when_api_key_missing(): void
    {
        Config::set('services.openai.key', null);

        $file = UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg');

        $this->actingAs($this->user)
            ->from(route('advance-expenses'))
            ->post(route('receipt-scans.store'), ['file' => $file])
            ->assertRedirect(route('advance-expenses'))
            ->assertSessionHasErrors('receipt');
    }

    public function test_scan_returns_extracted_fields(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'entry_date' => '2025-05-15',
                                'amount' => 1080,
                                'merchant_name' => 'セブン-イレブン',
                                'confidence_date' => 0.95,
                                'confidence_amount' => 0.9,
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ]),
        ]);

        $file = UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg');

        $this->actingAs($this->user)
            ->from(route('advance-expenses'))
            ->post(route('receipt-scans.store'), ['file' => $file])
            ->assertRedirect(route('advance-expenses'))
            ->assertSessionHas('receiptScan', fn (array $scan) => $scan['entry_date'] === '2025-05-15'
                && $scan['amount'] === 1080
                && $scan['merchant_name'] === 'セブン-イレブン');
    }

    public function test_scan_rejects_when_both_fields_missing(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'entry_date' => null,
                                'amount' => null,
                                'merchant_name' => null,
                                'confidence_date' => null,
                                'confidence_amount' => null,
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ]),
        ]);

        $file = UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg');

        $this->actingAs($this->user)
            ->from(route('advance-expenses'))
            ->post(route('receipt-scans.store'), ['file' => $file])
            ->assertRedirect(route('advance-expenses'))
            ->assertSessionHasErrors('receipt');
    }
}
