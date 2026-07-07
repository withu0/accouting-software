<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class PingOpenAiCommand extends Command
{
    protected $signature = 'openai:ping';

    protected $description = 'Test connectivity to the OpenAI API from this server';

    public function handle(): int
    {
        $apiKey = config('services.openai.key');
        if (! is_string($apiKey) || $apiKey === '') {
            $this->error('OPENAI_API_KEY is not configured.');

            return self::FAILURE;
        }

        $this->info('OpenAI config:');
        $this->line('  model: '.config('services.openai.receipt_model', 'gpt-4o'));
        $this->line('  http_verify: '.(config('services.openai.http_verify', true) ? 'true' : 'false'));
        $this->line('  curl loaded: '.(extension_loaded('curl') ? 'yes' : 'no'));
        $this->newLine();

        $client = Http::withToken($apiKey)
            ->timeout(30)
            ->connectTimeout(15);

        if (! config('services.openai.http_verify', true)) {
            $client = $client->withoutVerifying();
        }

        try {
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.receipt_model', 'gpt-4o'),
                'max_tokens' => 5,
                'messages' => [
                    ['role' => 'user', 'content' => 'Reply with OK only.'],
                ],
            ]);
        } catch (ConnectionException $exception) {
            $this->error('Connection failed: '.$exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error(sprintf('Request failed (%s): %s', $exception::class, $exception->getMessage()));

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('HTTP '.$response->status().': '.($response->json('error.message') ?? $response->body()));

            return self::FAILURE;
        }

        $this->info('OpenAI API connection successful (HTTP '.$response->status().').');

        return self::SUCCESS;
    }
}
