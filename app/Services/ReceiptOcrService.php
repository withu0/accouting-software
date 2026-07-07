<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ReceiptOcrService
{
    /**
     * @return array{
     *     entry_date: string|null,
     *     amount: int|null,
     *     merchant_name: string|null,
     *     confidence: array{date: float|null, amount: float|null}
     * }
     */
    public function extract(UploadedFile $file): array
    {
        $apiKey = config('services.openai.key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new InvalidArgumentException('OpenAI APIキーが設定されていません。');
        }

        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw new RuntimeException('アップロードされた画像を読み込めませんでした。');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('アップロードされた画像を読み込めませんでした。');
        }

        $mimeType = $this->resolveMimeType($file);
        $base64 = base64_encode($contents);
        $model = config('services.openai.receipt_model', 'gpt-4o');
        $detail = config('services.openai.receipt_image_detail', 'high');

        try {
            $response = $this->httpClient()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'receipt_scan',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'entry_date' => [
                                        'type' => ['string', 'null'],
                                        'description' => 'Receipt date in YYYY-MM-DD format',
                                    ],
                                    'amount' => [
                                        'type' => ['integer', 'null'],
                                        'description' => 'Tax-included total amount in JPY',
                                    ],
                                    'merchant_name' => [
                                        'type' => ['string', 'null'],
                                        'description' => 'Store or merchant name',
                                    ],
                                    'confidence_date' => [
                                        'type' => ['number', 'null'],
                                        'description' => 'Confidence for entry_date from 0 to 1',
                                    ],
                                    'confidence_amount' => [
                                        'type' => ['number', 'null'],
                                        'description' => 'Confidence for amount from 0 to 1',
                                    ],
                                ],
                                'required' => [
                                    'entry_date',
                                    'amount',
                                    'merchant_name',
                                    'confidence_date',
                                    'confidence_amount',
                                ],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => implode("\n", [
                                'You extract fields from Japanese receipts (領収書, レシート).',
                                'Return entry_date as YYYY-MM-DD when clearly readable, otherwise null.',
                                'Return amount as the tax-included total (合計, 総計, お支払い, ご利用金額). Integer yen only.',
                                'Ignore subtotals (小計) when a grand total exists.',
                                'Return merchant_name when visible, otherwise null.',
                                'If unsure about date or amount, return null for that field rather than guessing.',
                            ]),
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => 'Extract the receipt date, tax-included total amount, and merchant name.'],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$mimeType};base64,{$base64}",
                                        'detail' => $detail,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException($this->connectionErrorMessage($exception), previous: $exception);
        } catch (Throwable $exception) {
            Log::error('Receipt OCR request failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException($this->requestFailureMessage($exception), previous: $exception);
        }

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? '領収書の読み取りに失敗しました。';

            throw new RuntimeException($message);
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || $content === '') {
            throw new RuntimeException('領収書の読み取り結果を取得できませんでした。');
        }

        /** @var array<string, mixed>|null $parsed */
        $parsed = json_decode($content, true);
        if (! is_array($parsed)) {
            throw new RuntimeException('領収書の読み取り結果の形式が不正です。');
        }

        return $this->normalizeResult($parsed);
    }

    private function httpClient()
    {
        $client = Http::withToken((string) config('services.openai.key'))
            ->timeout((int) config('services.openai.receipt_timeout', 60))
            ->connectTimeout(15);

        if (! config('services.openai.http_verify', true)) {
            return $client->withoutVerifying();
        }

        return $client;
    }

    private function resolveMimeType(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';

        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return $mimeType;
        }

        return match (strtolower($file->getClientOriginalExtension())) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    private function connectionErrorMessage(ConnectionException $exception): string
    {
        $detail = $exception->getMessage();

        if (str_contains($detail, 'SSL certificate') || str_contains($detail, 'certificate verify failed')) {
            return 'OpenAI APIへの接続に失敗しました（SSL証明書）。サーバーの .env に OPENAI_HTTP_VERIFY=false を設定するか、PHP の CA 証明書を設定してください。';
        }

        if (str_contains($detail, 'timed out') || str_contains($detail, 'Timeout')) {
            return 'OpenAI APIへの接続がタイムアウトしました。時間をおいて再度お試しください。';
        }

        return 'OpenAI APIへの接続に失敗しました。ネットワーク設定を確認してください。';
    }

    private function requestFailureMessage(Throwable $exception): string
    {
        if (config('app.debug')) {
            return sprintf('OpenAI APIエラー (%s): %s', class_basename($exception), $exception->getMessage());
        }

        $detail = $exception->getMessage();

        if (str_contains($detail, 'curl extension') || str_contains($detail, 'cURL extension')) {
            return 'サーバーで PHP の cURL 拡張が有効になっていません。ホスティング提供者にご確認ください。';
        }

        if (str_contains($detail, 'SSL certificate') || str_contains($detail, 'certificate verify failed')) {
            return 'OpenAI APIへの接続に失敗しました（SSL証明書）。.env の OPENAI_HTTP_VERIFY=false を設定し、php artisan config:clear を実行してください。';
        }

        if (str_contains($detail, 'Could not resolve host') || str_contains($detail, 'cURL error 6')) {
            return 'OpenAI APIのホスト名を解決できません。サーバーから api.openai.com への外部接続が許可されているかホスティング提供者にご確認ください。';
        }

        if (str_contains($detail, 'Failed to connect') || str_contains($detail, 'cURL error 7')) {
            return 'OpenAI APIへの接続が拒否されました。サーバーから api.openai.com への外部 HTTPS 接続が許可されているかホスティング提供者にご確認ください。';
        }

        if (str_contains($detail, 'timed out') || str_contains($detail, 'cURL error 28')) {
            return 'OpenAI APIへの接続がタイムアウトしました。時間をおいて再度お試しください。';
        }

        return 'OpenAI APIへの接続中にエラーが発生しました。';
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{
     *     entry_date: string|null,
     *     amount: int|null,
     *     merchant_name: string|null,
     *     confidence: array{date: float|null, amount: float|null}
     * }
     */
    private function normalizeResult(array $parsed): array
    {
        $entryDate = $parsed['entry_date'] ?? null;
        $amount = $parsed['amount'] ?? null;
        $merchantName = $parsed['merchant_name'] ?? null;

        $normalizedDate = is_string($entryDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate) === 1
            ? $entryDate
            : null;

        $normalizedAmount = is_int($amount) && $amount > 0
            ? $amount
            : (is_numeric($amount) && (int) $amount > 0 ? (int) $amount : null);

        $normalizedMerchant = is_string($merchantName) && trim($merchantName) !== ''
            ? trim($merchantName)
            : null;

        return [
            'entry_date' => $normalizedDate,
            'amount' => $normalizedAmount,
            'merchant_name' => $normalizedMerchant,
            'confidence' => [
                'date' => $this->normalizeConfidence($parsed['confidence_date'] ?? null),
                'amount' => $this->normalizeConfidence($parsed['confidence_amount'] ?? null),
            ],
        ];
    }

    private function normalizeConfidence(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $confidence = (float) $value;

        return max(0.0, min(1.0, $confidence));
    }
}
