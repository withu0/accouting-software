<?php

namespace Tests\Unit;

use App\Services\ReceiptOcrService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReceiptOcrServiceTest extends TestCase
{
    public function test_extract_parses_openai_response(): void
    {
        Config::set('services.openai.key', 'test-openai-key');

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'entry_date' => '2025-05-15',
                                'amount' => 1080,
                                'merchant_name' => 'テスト商店',
                                'consumption_tax_category' => 'taxable_purchase_10',
                                'confidence_date' => 0.9,
                                'confidence_amount' => 0.85,
                                'confidence_consumption_tax_category' => 0.88,
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ]),
        ]);

        $service = new ReceiptOcrService;
        $file = UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg');

        $result = $service->extract($file);

        $this->assertSame('2025-05-15', $result['entry_date']);
        $this->assertSame(1080, $result['amount']);
        $this->assertSame('テスト商店', $result['merchant_name']);
        $this->assertSame('taxable_purchase_10', $result['consumption_tax_category']);
        $this->assertSame(0.9, $result['confidence']['date']);
        $this->assertSame(0.85, $result['confidence']['amount']);
        $this->assertSame(0.88, $result['confidence']['consumption_tax_category']);
    }

    public function test_extract_discards_invalid_tax_category(): void
    {
        Config::set('services.openai.key', 'test-openai-key');

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'entry_date' => '2025-05-15',
                                'amount' => 1080,
                                'merchant_name' => 'テスト商店',
                                'consumption_tax_category' => 'invalid_category',
                                'confidence_date' => 0.9,
                                'confidence_amount' => 0.85,
                                'confidence_consumption_tax_category' => 0.5,
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ]),
        ]);

        $service = new ReceiptOcrService;
        $file = UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg');

        $result = $service->extract($file);

        $this->assertNull($result['consumption_tax_category']);
    }
}
