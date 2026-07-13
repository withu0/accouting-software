<?php

namespace Tests\Unit\CreditCardCsv;

use App\Enums\CreditCardCsvFormat;
use App\Services\BankCsv\BankCsvEncodingNormalizer;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use App\Services\CreditCardCsv\CreditCardCsvParser;
use InvalidArgumentException;
use Tests\TestCase;

class SaisonCreditCardCsvParserTest extends TestCase
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

    public function test_rejects_unrecognized_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('対応していないCSV形式です');

        $this->parser->parse("foo,bar,baz\n1,2,3");
    }

    public function test_rejects_empty_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CSVファイルが空です');

        $this->parser->parse('');
    }
}
