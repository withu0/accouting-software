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
                                'confidence_date' => 0.9,
                                'confidence_amount' => 0.85,
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
        $this->assertSame(0.9, $result['confidence']['date']);
        $this->assertSame(0.85, $result['confidence']['amount']);
    }
}
