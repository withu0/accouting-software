<?php

namespace Tests\Unit\CreditCardCsv;

use App\Enums\CreditCardCsvFormat;
use App\Services\BankCsv\BankCsvEncodingNormalizer;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use App\Services\CreditCardCsv\CreditCardCsvParser;
use Tests\TestCase;

class GenericCreditCardCsvParserTest extends TestCase
{
    private CreditCardCsvParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new CreditCardCsvParser(
            new BankCsvEncodingNormalizer,
            new BankCsvRowBuilder,
        );
    }

    public function test_parses_generic_column_layout(): void
    {
        $csv = <<<'CSV'
カード名,Visa Business Card
お支払日,2026/07/06
請求額,00015000

利用日,利用店名,金額
2026/05/10,AMAZON.CO.JP,5000
2026/05/11,スターバックス,680
2026/05/12,ETC利用,220
,【合計】,,15000
CSV;

        $result = $this->parser->parse($csv);

        $this->assertSame(CreditCardCsvFormat::Generic, $result['format']);
        $this->assertSame('Visa Business Card', $result['card_name']);
        $this->assertEquals('2026-07-06', $result['payment_date']?->format('Y-m-d'));
        $this->assertSame(15000, $result['billing_amount']);
        $this->assertCount(3, $result['rows']);
        $this->assertEquals('AMAZON.CO.JP', $result['rows'][0]['description']);
        $this->assertSame(5000, $result['rows'][0]['amount']);
    }

    public function test_saison_sample_still_uses_saison_adapter(): void
    {
        $content = file_get_contents(base_path('samples/credit-card-sample/SAISON_2607.csv'));
        $result = $this->parser->parse($content);

        $this->assertSame(CreditCardCsvFormat::Saison, $result['format']);
    }
}
