<?php

namespace Tests\Unit\CreditCardCsv;

use App\Enums\CreditCardCsvFormat;
use App\Services\BankCsv\BankCsvEncodingNormalizer;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use App\Services\CreditCardCsv\CreditCardCsvParser;
use App\Services\CreditCardCsv\Support\CreditCardCsvAiColumnMapper;
use InvalidArgumentException;
use Tests\TestCase;

class SaisonCreditCardCsvParserTest extends TestCase
{
    private CreditCardCsvParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.key' => null]);

        $rowBuilder = new BankCsvRowBuilder;
        $this->parser = new CreditCardCsvParser(
            new BankCsvEncodingNormalizer,
            $rowBuilder,
            new CreditCardCsvAiColumnMapper($rowBuilder),
        );
    }

    public function test_parses_saison_sample_csv(): void
    {
        $content = file_get_contents(base_path('samples/credit-card-sample/SAISON_2607.csv'));
        $result = $this->parser->parse($content);

        $this->assertSame(CreditCardCsvFormat::Saison, $result['format']);
        $this->assertSame('セゾンプラチナビジネス・アメリカンエキスプレスカード', $result['card_name']);
        $this->assertEquals('2026-07-06', $result['payment_date']?->format('Y-m-d'));
        $this->assertSame(522043, $result['billing_amount']);

        $rows = $result['rows'];
        $this->assertCount(15, $rows);

        $this->assertEquals('2026-05-10', $rows[0]['transaction_date']->format('Y-m-d'));
        $this->assertEquals('ETC時間帯割引(名島→名島)', $rows[0]['description']);
        $this->assertSame(560, $rows[0]['amount']);
        $this->assertNotEmpty($rows[0]['row_hash']);

        $this->assertEquals('2026-05-27', $rows[14]['transaction_date']->format('Y-m-d'));
        $this->assertEquals('FACEBK *XVHT8R52Z2', $rows[14]['description']);
        $this->assertSame(94752, $rows[14]['amount']);

        $descriptions = array_column($rows, 'description');
        $this->assertNotContains('【小計】', $descriptions);
        $this->assertNotContains('【合計】', $descriptions);
        foreach ($descriptions as $description) {
            $this->assertStringNotContainsString('ご利用者名:', $description);
        }
    }

    public function test_skips_negative_amount_rows(): void
    {
        $csv = <<<'CSV'
カード名称,セゾンテストカード
お支払日,2026/07/06
今回ご請求額,00010000

利用日,ご利用店名及び商品名,利用者,支払区分,利用金額,締前入金額
2026/05/10,AMAZON.CO.JP,本人,1回払い,5000,
2026/05/11,前回分口座振替金額,本人,1回払い,-3000,
2026/05/12,スターバックス,本人,1回払い,680,
CSV;

        $result = $this->parser->parse($csv);

        $this->assertSame(CreditCardCsvFormat::Saison, $result['format']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame(5000, $result['rows'][0]['amount']);
        $this->assertSame(680, $result['rows'][1]['amount']);
    }

    public function test_rejects_unrecognized_format_without_openai_key(): void
    {
        config(['services.openai.key' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY');

        $this->parser->parse("foo,bar,baz\n1,2,3");
    }

    public function test_rejects_empty_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CSVファイルが空です');

        $this->parser->parse('');
    }
}
