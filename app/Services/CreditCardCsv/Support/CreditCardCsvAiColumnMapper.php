<?php

namespace App\Services\CreditCardCsv\Support;

use App\Services\BankCsv\Support\BankCsvRowBuilder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class CreditCardCsvAiColumnMapper
{
    public function __construct(
        private readonly BankCsvRowBuilder $rowBuilder,
    ) {}

    public function isAvailable(): bool
    {
        $apiKey = config('services.openai.key');

        return is_string($apiKey) && $apiKey !== '';
    }

    /**
     * Ask OpenAI whether the CSV is a credit card statement and map columns.
     *
     * @param  array<int, string>  $lines
     * @return array{
     *     index: int,
     *     headers: array<int, string>,
     *     date_index: int,
     *     description_index: int,
     *     amount_index: int,
     * }
     */
    public function mapColumns(array $lines): array
    {
        if (! $this->isAvailable()) {
            throw new InvalidArgumentException(
                '未知のCSV形式です。AI列判定を使うには OPENAI_API_KEY を設定してください。'
            );
        }

        $sample = $this->buildSample($lines);
        if ($sample === []) {
            throw new InvalidArgumentException('CSVファイルが空です。');
        }

        try {
            $mapped = $this->requestMapping(array_column($sample, 'content'));
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Credit card CSV AI column mapping failed.', [
                'message' => $exception->getMessage(),
            ]);

            throw new InvalidArgumentException($this->userFacingError($exception));
        }

        $sampleHeaderIndex = $mapped['header_row_index'];
        if (! isset($sample[$sampleHeaderIndex])) {
            throw new InvalidArgumentException(
                'クレジットカード明細CSVとして列を判別できませんでした。別のファイルをお試しください。'
            );
        }

        $headerIndex = $sample[$sampleHeaderIndex]['line_index'];
        $headers = $this->rowBuilder->parseCsvLine(trim($lines[$headerIndex]));
        $dateIndex = $mapped['date_column_index'];
        $descriptionIndex = $mapped['description_column_index'];
        $amountIndex = $mapped['amount_column_index'];

        if (
            ! isset($headers[$dateIndex], $headers[$descriptionIndex], $headers[$amountIndex])
            || count(array_unique([$dateIndex, $descriptionIndex, $amountIndex])) < 3
        ) {
            throw new InvalidArgumentException(
                'クレジットカード明細CSVとして列を判別できませんでした。別のファイルをお試しください。'
            );
        }

        return [
            'index' => $headerIndex,
            'headers' => $headers,
            'date_index' => $dateIndex,
            'description_index' => $descriptionIndex,
            'amount_index' => $amountIndex,
        ];
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, array{line_index: int, content: string}>
     */
    private function buildSample(array $lines): array
    {
        $sample = [];

        foreach ($lines as $lineIndex => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $sample[] = [
                'line_index' => $lineIndex,
                'content' => $trimmed,
            ];
            if (count($sample) >= 12) {
                break;
            }
        }

        return $sample;
    }

    /**
     * @param  array<int, string>  $sampleLines
     * @return array{
     *     header_row_index: int,
     *     date_column_index: int,
     *     description_column_index: int,
     *     amount_column_index: int,
     * }
     */
    private function requestMapping(array $sampleLines): array
    {
        $numbered = [];
        foreach ($sampleLines as $index => $line) {
            $numbered[] = "{$index}: {$line}";
        }

        $response = $this->httpClient()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.csv_model', config('services.openai.receipt_model', 'gpt-4o')),
                'temperature' => 0,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'credit_card_csv_column_map',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'is_credit_card_csv' => [
                                    'type' => 'boolean',
                                    'description' => 'True only if this looks like a credit card statement transaction CSV',
                                ],
                                'rejection_reason' => [
                                    'type' => ['string', 'null'],
                                    'description' => 'Short Japanese reason when is_credit_card_csv is false',
                                ],
                                'header_row_index' => [
                                    'type' => 'integer',
                                    'description' => '0-based index among the provided sample lines for the header row',
                                ],
                                'date_column_index' => [
                                    'type' => 'integer',
                                    'description' => '0-based column index for the transaction/利用日 date',
                                ],
                                'description_column_index' => [
                                    'type' => 'integer',
                                    'description' => '0-based column index for merchant/利用内容 description',
                                ],
                                'amount_column_index' => [
                                    'type' => 'integer',
                                    'description' => '0-based column index for the yen amount/金額',
                                ],
                            ],
                            'required' => [
                                'is_credit_card_csv',
                                'rejection_reason',
                                'header_row_index',
                                'date_column_index',
                                'description_column_index',
                                'amount_column_index',
                            ],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => implode("\n", [
                            'You analyze uploaded CSV samples for Japanese credit card statements (クレジットカード明細).',
                            'First decide is_credit_card_csv:',
                            '- true: file contains card purchase/settlement transactions with date, merchant, and amount columns',
                            '- false: bank CSV, unrelated spreadsheet, empty/garbled junk, or cannot identify required columns',
                            'When false, set rejection_reason in concise Japanese (1 short sentence) and set column indexes to 0.',
                            'When true, map columns for: utilization date (利用日/ご利用日), merchant/description (店名/ご利用内容), yen amount (金額/利用金額).',
                            'Prefer utilization date over processing/payment/billing dates.',
                            'Prefer local yen amount over foreign-currency amount columns.',
                            'header_row_index is the 0-based index within the SAMPLE LINES provided (not the whole file).',
                            'If the first sample line already looks like headers, use 0.',
                        ]),
                    ],
                    [
                        'role' => 'user',
                        'content' => "Sample CSV lines (index: content):\n".implode("\n", $numbered),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI API returned HTTP '.$response->status());
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || $content === '') {
            throw new RuntimeException('OpenAI API returned an empty response.');
        }

        /** @var array<string, mixed>|null $parsed */
        $parsed = json_decode($content, true);
        if (! is_array($parsed)) {
            throw new RuntimeException('OpenAI API returned an invalid JSON response.');
        }

        if (($parsed['is_credit_card_csv'] ?? false) !== true) {
            $reason = is_string($parsed['rejection_reason'] ?? null) && trim($parsed['rejection_reason']) !== ''
                ? trim($parsed['rejection_reason'])
                : 'クレジットカード明細CSVではないと判断されました。';

            throw new InvalidArgumentException(
                'アップロードされたファイルはクレジットカード明細CSVとして認識できませんでした。'.$reason
            );
        }

        foreach (['header_row_index', 'date_column_index', 'description_column_index', 'amount_column_index'] as $key) {
            if (! is_int($parsed[$key] ?? null) && ! is_numeric($parsed[$key] ?? null)) {
                throw new InvalidArgumentException(
                    'クレジットカード明細CSVとして列を判別できませんでした。別のファイルをお試しください。'
                );
            }
            $parsed[$key] = (int) $parsed[$key];
        }

        return [
            'header_row_index' => $parsed['header_row_index'],
            'date_column_index' => $parsed['date_column_index'],
            'description_column_index' => $parsed['description_column_index'],
            'amount_column_index' => $parsed['amount_column_index'],
        ];
    }

    private function httpClient()
    {
        $client = Http::withToken((string) config('services.openai.key'))
            ->timeout((int) config('services.openai.csv_timeout', config('services.openai.receipt_timeout', 60)))
            ->connectTimeout(15);

        if (! config('services.openai.http_verify', true)) {
            return $client->withoutVerifying();
        }

        return $client;
    }

    private function userFacingError(Throwable $exception): string
    {
        if ($exception instanceof ConnectionException) {
            return 'CSV列のAI判定でOpenAI APIへ接続できませんでした。時間をおいて再度お試しください。';
        }

        if (config('app.debug')) {
            return 'CSV列のAI判定に失敗しました: '.$exception->getMessage();
        }

        return 'CSV列のAI判定に失敗しました。時間をおいて再度お試しください。';
    }
}
