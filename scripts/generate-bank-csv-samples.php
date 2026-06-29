<?php

declare(strict_types=1);

$base = __DIR__.'/../samples/bank-csv-samples';

$aprilNative = <<<'CSV'
日付,摘要,入金額,出金額,残高
2025-04-01,振込 カ）ABC商事,250000,,2250000
2025-04-05,Amazon.co.jp,,12800,2237200
2025-04-08,JR東日本 出張,,4520,2232680
2025-04-12,NTTファイナンス 請求,,8800,2223880
2025-04-15,振込 カ）山田デザイン,180000,,2403880
2025-04-18,Google Ads,,15000,2388880
2025-04-22,振込 カ）XYZ株式会社,320000,,2708880
2025-04-25,Amazon.co.jp,,6500,2702380
2025-04-28,税務署 源泉所得税,,45000,2657380
2025-04-30,口座管理手数料,,1100,2656280
CSV;

$mayNative = <<<'CSV'
日付,摘要,入金額,出金額,残高
2025-05-02,振込 カ）DEF工業,150000,,2806280
2025-05-06,日本年金機構 厚生年金,,98000,2708280
2025-05-10,JR東日本,,2100,2706180
2025-05-14,Amazon.co.jp,,9200,2696980
2025-05-18,振込 カ）GHIサービス,95000,,2791980
2025-05-22,NTTコミュニケーションズ,,6600,2785380
2025-05-26,Google Workspace,,1980,2783400
2025-05-30,会議費 レストラン代,,12000,2771400
CSV;

file_put_contents("{$base}/native-2025-04.csv", $aprilNative);
file_put_contents("{$base}/native-2025-05.csv", $mayNative);

$gmoNativeApril = <<<'CSV'
日付,摘要,入金額,出金額,残高,メモ
2025-04-01,振込 カ）ABC商事,250000,,2250000,
2025-04-05,Amazon.co.jp,,12800,2237200,
2025-04-08,JR東日本 出張,,4520,2232680,
2025-04-15,振込 カ）山田デザイン,180000,,2403880,
2025-04-22,振込 カ）XYZ株式会社,320000,,2708880,
CSV;

$gmoNativeMay = <<<'CSV'
日付,摘要,入金額,出金額,残高,メモ
2025-05-02,振込 カ）テスト商事,100000,,2808880,
2025-05-10,Amazon.co.jp,,5000,2803880,
2025-05-15,JR東日本,,3200,2800680,
2025-05-20,振込 カ）デザイン工房,150000,,2950680,
CSV;

writeShiftJis("{$base}/gmo-native-2025-04.csv", $gmoNativeApril);
writeShiftJis("{$base}/gmo-native-2025-05.csv", $gmoNativeMay);

$rakutenApril = <<<'CSV'
取引日,入出金(円),残高(円),入出金先内容
2025/04/01,250000,2250000,振込 カ）ABC商事
2025/04/05,-12800,2237200,Amazon.co.jp
2025/04/08,-4520,2232680,JR東日本 出張
2025/04/15,180000,2403880,振込 カ）山田デザイン
2025/04/22,320000,2708880,振込 カ）XYZ株式会社
CSV;

$rakutenMay = <<<'CSV'
取引日,入出金(円),残高(円),入出金先内容
2025/05/02,100000,2808880,振込 カ）テスト商事
2025/05/10,-5000,2803880,Amazon.co.jp
2025/05/15,-3200,2800680,JR東日本
2025/05/20,150000,2950680,振込 カ）デザイン工房
CSV;

writeShiftJis("{$base}/rakuten-2025-04.csv", $rakutenApril);
writeShiftJis("{$base}/rakuten-2025-05.csv", $rakutenMay);

$sbiApril = <<<'CSV'
日付,内容,出金金額(円),入金金額(円),残高(円),メモ
,,,,,
,,,,,
2025/04/01,振込 カ）ABC商事,,250000,2250000,
2025/04/05,Amazon.co.jp,12800,,2237200,
2025/04/08,JR東日本 出張,4520,,2232680,
2025/04/15,振込 カ）山田デザイン,,180000,2403880,
2025/04/22,振込 カ）XYZ株式会社,,320000,2708880,
CSV;

$sbiMay = <<<'CSV'
日付,内容,出金金額(円),入金金額(円),残高(円),メモ
,,,,,
2025/05/02,振込 カ）テスト商事,,100000,2808880,
2025/05/10,Amazon.co.jp,5000,,2803880,
2025/05/15,JR東日本,3200,,2800680,
2025/05/20,振込 カ）デザイン工房,,150000,2950680,
CSV;

writeShiftJis("{$base}/sbi-2025-04.csv", $sbiApril);
writeShiftJis("{$base}/sbi-2025-05.csv", $sbiMay);

$zenginDataApril = [
    ['070401', '1', '250000', '振込 カ）ABC商事'],
    ['070405', '2', '12800', 'Amazon.co.jp'],
    ['070408', '2', '4520', 'JR東日本 出張'],
    ['070415', '1', '180000', '振込 カ）山田デザイン'],
    ['070422', '1', '320000', '振込 カ）XYZ株式会社'],
];

$zenginDataMay = [
    ['070502', '1', '100000', '振込 カ）テスト商事'],
    ['070510', '2', '5000', 'Amazon.co.jp'],
    ['070515', '2', '3200', 'JR東日本'],
    ['070520', '1', '150000', '振込 カ）デザイン工房'],
];

writeShiftJis("{$base}/gmo-zengin-csv-2025-04.csv", buildZenginCsv($zenginDataApril, '070401', '070430'));
writeShiftJis("{$base}/gmo-zengin-csv-2025-05.csv", buildZenginCsv($zenginDataMay, '070501', '070531'));
writeShiftJis("{$base}/gmo-zengin-fixed-2025-04.txt", buildZenginFixed($zenginDataApril));
writeShiftJis("{$base}/gmo-zengin-fixed-2025-05.txt", buildZenginFixed($zenginDataMay));

echo "Generated sample bank CSV files.\n";

function writeShiftJis(string $path, string $utf8Content): void
{
    file_put_contents($path, mb_convert_encoding($utf8Content, 'SJIS-win', 'UTF-8'));
}

/**
 * @param  array<int, array{0: string, 1: string, 2: string, 3: string}>  $rows
 */
function buildZenginCsv(array $rows, string $from, string $to): string
{
    $lines = [];
    $lines[] = implode(',', [
        '1', '03', '0', $from, $from, $to, '0310', 'GMO AOZORA NET BANK', '101', '000', '1', '1234567890', 'TEST ACCOUNT', '1', '0', '2000000',
    ]);

    $seq = 1;
    foreach ($rows as [$date, $direction, $amount, $description]) {
        $fields = array_fill(0, 20, '');
        $fields[0] = '2';
        $fields[1] = str_pad((string) $seq, 8, '0', STR_PAD_LEFT);
        $fields[2] = $date;
        $fields[3] = $date;
        $fields[4] = $direction;
        $fields[5] = '11';
        $fields[6] = str_pad($amount, 12, '0', STR_PAD_LEFT);
        $fields[7] = '000000000000';
        $fields[14] = $direction === '1' ? $description : '';
        $fields[17] = $direction === '2' ? $description : '';
        $lines[] = implode(',', $fields);
        $seq++;
    }

    $depositCount = count(array_filter($rows, fn ($r) => $r[1] === '1'));
    $withdrawalCount = count($rows) - $depositCount;
    $depositTotal = array_sum(array_map(fn ($r) => $r[1] === '1' ? (int) $r[2] : 0, $rows));
    $withdrawalTotal = array_sum(array_map(fn ($r) => $r[1] === '2' ? (int) $r[2] : 0, $rows));

    $lines[] = implode(',', [
        '8',
        str_pad((string) $depositCount, 6, '0', STR_PAD_LEFT),
        str_pad((string) $depositTotal, 13, '0', STR_PAD_LEFT),
        str_pad((string) $withdrawalCount, 6, '0', STR_PAD_LEFT),
        str_pad((string) $withdrawalTotal, 13, '0', STR_PAD_LEFT),
        '1', '2708880', str_pad((string) count($rows), 7, '0', STR_PAD_LEFT),
    ]);
    $lines[] = '9';

    return implode("\r\n", $lines)."\r\n";
}

/**
 * @param  array<int, array{0: string, 1: string, 2: string, 3: string}>  $rows
 */
function buildZenginFixed(array $rows): string
{
    $lines = [];
    $lines[] = padRecord('1'.'03'.'0'.'070401'.'070401'.'070430'.'0310'.str_pad('GMO AOZORA NET', 15).str_pad('101', 3, '0', STR_PAD_LEFT).str_pad('MAIN BRANCH', 15).'000'.'1'.str_pad('1234567890', 10, '0', STR_PAD_LEFT).str_pad('TEST ACCOUNT', 40).'1'.'0'.str_pad('2000000', 14, '0', STR_PAD_LEFT));

    $seq = 1;
    foreach ($rows as [$date, $direction, $amount, $description]) {
        $record = '2';
        $record .= str_pad((string) $seq, 8, '0', STR_PAD_LEFT);
        $record .= $date.$date;
        $record .= $direction.'11';
        $record .= str_pad($amount, 12, '0', STR_PAD_LEFT);
        $record .= str_pad('0', 12, '0', STR_PAD_LEFT);
        $record .= str_pad('', 6);
        $record .= str_pad('', 6);
        $record .= str_pad('', 1);
        $record .= str_pad('', 7);
        $record .= str_pad('', 3);
        $record .= str_pad('', 10);
        $record .= str_pad($direction === '1' ? $description : '', 48);
        $record .= str_pad('', 15);
        $record .= str_pad('', 15);
        $record .= str_pad($direction === '2' ? $description : '', 20);
        $record .= str_pad('', 20);
        $lines[] = padRecord($record);
        $seq++;
    }

    $lines[] = padRecord('8'.str_pad('', 199));
    $lines[] = padRecord('9'.str_pad('', 199));

    return implode("\r\n", $lines)."\r\n";
}

function padRecord(string $record): string
{
    return str_pad(substr($record, 0, 200), 200, ' ');
}
