<?php

namespace Tests\Unit\CreditCardCsv;

use App\Enums\CreditCardCsvFormat;
use App\Services\BankCsv\BankCsvEncodingNormalizer;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use App\Services\CreditCardCsv\CreditCardCsvParser;
use App\Services\CreditCardCsv\Support\CreditCardCsvAiColumnMapper;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class CreditCardCsvAiColumnMapperTest extends TestCase
{
    private function makeParser(): CreditCardCsvParser
    {
        $rowBuilder = new BankCsvRowBuilder;

        return new CreditCardCsvParser(
            new BankCsvEncodingNormalizer,
            $rowBuilder,
            new CreditCardCsvAiColumnMapper($rowBuilder),
        );
    }

    public function test_known_header_names_parse_without_ai(): void
    {
        config(['services.openai.key' => null]);

        $csv = <<<'CSV'
ご利用日,データ処理日,ご利用内容,カード会員様名,会員番号 #,金額,海外通貨利用金額,換算レート
2026/04/06,2026/04/06,YELL LIMITED LIABILITY COMPANY,DAIKI NAKAI,-83001,"11,000",,
2026/03/26,2026/03/26,前回分口座振替金額,DAIKI NAKAI,-83001,"-361,429",,
2026/03/11,2026/03/14,エルわか　東京都　豊島区,DAIKI NAKAI,-83001,"26,800",,
CSV;

        $result = $this->makeParser()->parse($csv);

        $this->assertSame(CreditCardCsvFormat::Generic, $result['format']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame(11000, $result['rows'][0]['amount']);
        $this->assertSame(26800, $result['rows'][1]['amount']);
        Http::assertNothingSent();
    }

    public function test_unknown_format_uses_ai_mapping(): void
    {
        config(['services.openai.key' => 'test-openai-key']);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'is_credit_card_csv' => true,
                            'rejection_reason' => null,
                            'header_row_index' => 0,
                            'date_column_index' => 0,
                            'description_column_index' => 1,
                            'amount_column_index' => 2,
                        ]),
                    ],
                ]],
            ]),
        ]);

        $csv = <<<'CSV'
X,Y,Z
April 6 2026,Coffee Shop,1500
April 7 2026,Book Store,2200
CSV;

        $result = $this->makeParser()->parse($csv);

        $this->assertSame(CreditCardCsvFormat::AiMapped, $result['format']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame('Coffee Shop', $result['rows'][0]['description']);
        $this->assertSame(1500, $result['rows'][0]['amount']);
        Http::assertSentCount(1);
    }

    public function test_ai_rejects_non_credit_card_csv(): void
    {
        config(['services.openai.key' => 'test-openai-key']);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'is_credit_card_csv' => false,
                            'rejection_reason' => '銀行明細CSVのため取り込めません。',
                            'header_row_index' => 0,
                            'date_column_index' => 0,
                            'description_column_index' => 0,
                            'amount_column_index' => 0,
                        ]),
                    ],
                ]],
            ]),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('クレジットカード明細CSVとして認識できませんでした');

        $this->makeParser()->parse("foo,bar,baz\n1,2,3");
    }

    public function test_unknown_format_requires_openai_key(): void
    {
        config(['services.openai.key' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY');

        $this->makeParser()->parse("foo,bar,baz\n1,2,3");
    }
}
