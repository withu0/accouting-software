<?php

namespace Tests\Unit;

use App\Services\BankCsvParser;
use InvalidArgumentException;
use Tests\TestCase;

class BankCsvParserTest extends TestCase
{
    private BankCsvParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BankCsvParser;
    }

    public function test_parses_valid_csv(): void
    {
        $csv = <<<'CSV'
日付,摘要,入金額,出金額,残高
2025-04-01,振込 カ）ABC商事,100000,,1500000
2025-04-02,Amazon.co.jp,,5000,1495000
CSV;

        $rows = $this->parser->parse($csv);

        $this->assertCount(2, $rows);
        $this->assertEquals('2025-04-01', $rows[0]['transaction_date']->format('Y-m-d'));
        $this->assertEquals('振込 カ）ABC商事', $rows[0]['description']);
        $this->assertEquals(100000, $rows[0]['deposit_amount']);
        $this->assertEquals(0, $rows[0]['withdrawal_amount']);
        $this->assertEquals(1500000, $rows[0]['balance']);
        $this->assertNotEmpty($rows[0]['row_hash']);

        $this->assertEquals('2025-04-02', $rows[1]['transaction_date']->format('Y-m-d'));
        $this->assertEquals(5000, $rows[1]['withdrawal_amount']);
        $this->assertEquals(0, $rows[1]['deposit_amount']);
    }

    public function test_rejects_missing_header(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CSV is missing required header');

        $this->parser->parse("日付,摘要,入金額\n2025-04-01,test,1000");
    }

    public function test_rejects_both_amounts_set(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly one of deposit or withdrawal');

        $csv = <<<'CSV'
日付,摘要,入金額,出金額,残高
2025-04-01,test,1000,500,1500000
CSV;

        $this->parser->parse($csv);
    }

    public function test_rejects_empty_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CSV file is empty');

        $this->parser->parse('');
    }

    public function test_row_hash_is_deterministic(): void
    {
        $hash1 = $this->parser->computeRowHash('2025-04-01', 'Amazon.co.jp', 0, 5000, 1495000);
        $hash2 = $this->parser->computeRowHash('2025-04-01', 'Amazon.co.jp', 0, 5000, 1495000);

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1));
    }
}
