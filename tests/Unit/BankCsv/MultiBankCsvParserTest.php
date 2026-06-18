<?php

namespace Tests\Unit\BankCsv;

use App\Enums\BankCsvFormat;
use App\Services\BankCsv\BankCsvEncodingNormalizer;
use App\Services\BankCsv\BankCsvFormatDetector;
use App\Services\BankCsv\BankCsvParser;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use App\Services\BankCsv\Support\ZenginDateParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MultiBankCsvParserTest extends TestCase
{
    private BankCsvParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $builder = new BankCsvRowBuilder;
        $this->parser = new BankCsvParser(
            new BankCsvEncodingNormalizer,
            new BankCsvFormatDetector($builder),
            $builder,
        );
    }

    /**
     * @return array<string, array{0: string, 1: BankCsvFormat, 2: int}>
     */
    public static function sampleFileProvider(): array
    {
        return [
            'native april' => ['native-2025-04.csv', BankCsvFormat::Native, 5],
            'native may' => ['native-2025-05.csv', BankCsvFormat::Native, 4],
            'gmo native april' => ['gmo-native-2025-04.csv', BankCsvFormat::GmoNative, 5],
            'gmo native may' => ['gmo-native-2025-05.csv', BankCsvFormat::GmoNative, 4],
            'rakuten april' => ['rakuten-2025-04.csv', BankCsvFormat::Rakuten, 5],
            'rakuten may' => ['rakuten-2025-05.csv', BankCsvFormat::Rakuten, 4],
            'sbi april' => ['sbi-2025-04.csv', BankCsvFormat::SbiSumishin, 5],
            'sbi may' => ['sbi-2025-05.csv', BankCsvFormat::SbiSumishin, 4],
            'gmo zengin csv april' => ['gmo-zengin-csv-2025-04.csv', BankCsvFormat::GmoZenginCsv, 5],
            'gmo zengin csv may' => ['gmo-zengin-csv-2025-05.csv', BankCsvFormat::GmoZenginCsv, 4],
            'gmo zengin fixed april' => ['gmo-zengin-fixed-2025-04.txt', BankCsvFormat::GmoZenginFixed, 5],
            'gmo zengin fixed may' => ['gmo-zengin-fixed-2025-05.txt', BankCsvFormat::GmoZenginFixed, 4],
        ];
    }

    #[DataProvider('sampleFileProvider')]
    public function test_parses_sample_files(string $filename, BankCsvFormat $expectedFormat, int $expectedRows): void
    {
        $content = file_get_contents(base_path("samples/bank-csv-samples/{$filename}"));
        $result = $this->parser->parse($content);

        $this->assertSame($expectedFormat, $result['format']);
        $this->assertCount($expectedRows, $result['rows']);

        foreach ($result['rows'] as $row) {
            $this->assertNotEmpty($row['description']);
            $this->assertTrue(
                ($row['deposit_amount'] > 0 && $row['withdrawal_amount'] === 0)
                || ($row['withdrawal_amount'] > 0 && $row['deposit_amount'] === 0),
            );
        }
    }

    public function test_zengin_date_parser_reiwa(): void
    {
        $date = ZenginDateParser::parse('070401');

        $this->assertSame('2025-04-01', $date->format('Y-m-d'));
    }
}
