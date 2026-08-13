<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin Gemini generateContent client.
 * Failures are surfaced as RuntimeException with a stable reason code.
 */
class GeminiClient
{
    public const REASON_DISABLED = 'ai_disabled';

    public const REASON_MISSING_KEY = 'missing_api_key';

    public const REASON_TIMEOUT = 'timeout';

    public const REASON_RATE_LIMIT = 'rate_limit';

    public const REASON_QUOTA = 'quota_exceeded';

    public const REASON_UNAVAILABLE = 'service_unavailable';

    public const REASON_NETWORK = 'network_error';

    public const REASON_INVALID_RESPONSE = 'invalid_response';

    public function __construct(
        private readonly \App\Modules\AI\Services\AiSettingsService $aiSettings,
    ) {
    }

    /**
     * @param  array<string, mixed>  $generationConfig
     * @return array{text: string, raw: array<string, mixed>}
     */
    public function generateJson(string $systemInstruction, string $userPrompt, array $generationConfig = []): array
    {
        if (! $this->aiSettings->enabled()) {
            throw $this->failure(self::REASON_DISABLED, 'AI travel assistant is disabled.');
        }

        $apiKey = $this->aiSettings->apiKey();
        if ($apiKey === '') {
            throw $this->failure(self::REASON_MISSING_KEY, 'GEMINI_API_KEY is not configured.');
        }

        $model = $this->aiSettings->model();
        $baseUrl = $this->aiSettings->baseUrl();
        $timeout = $this->aiSettings->timeout();

        $url = sprintf('%s/models/%s:generateContent', $baseUrl, $model);

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userPrompt],
                    ],
                ],
            ],
            'generationConfig' => array_merge([
                'temperature' => $this->aiSettings->temperature(),
                'maxOutputTokens' => $this->aiSettings->maxOutputTokens(),
                'responseMimeType' => 'application/json',
            ], $generationConfig),
        ];

        try {
            $response = Http::timeout($timeout)
                ->withOptions(['verify' => $this->sslVerifyOption()])
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                ])
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            throw $this->failure(self::REASON_TIMEOUT, $exception->getMessage(), $exception);
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            if (str_contains(strtolower($message), 'ssl certificate')) {
                $message .= ' Set GEMINI_CA_BUNDLE in .env to storage/certs/cacert.pem (Windows/local PHP).';
            }

            throw $this->failure(self::REASON_NETWORK, $message, $exception);
        }

        if ($response->status() === 429) {
            $body = strtolower($response->body());
            $reason = str_contains($body, 'quota')
                ? self::REASON_QUOTA
                : self::REASON_RATE_LIMIT;
            throw $this->failure($reason, 'Gemini rate/quota limit reached.');
        }

        if ($response->serverError()) {
            throw $this->failure(self::REASON_UNAVAILABLE, 'Gemini service unavailable.');
        }

        if ($response->failed()) {
            $apiMessage = data_get($response->json(), 'error.message');
            Log::warning('Gemini API request failed', [
                'status' => $response->status(),
                'model' => $model,
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            $message = is_string($apiMessage) && $apiMessage !== ''
                ? $apiMessage
                : 'Gemini request failed.';

            if ($response->status() === 404 && str_contains(strtolower($message), 'model')) {
                $message .= ' Try GEMINI_MODEL=gemini-flash-lite-latest in .env.';
            }

            throw $this->failure(self::REASON_UNAVAILABLE, $message);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $text = $this->extractText($json);

        if ($text === '') {
            throw $this->failure(self::REASON_INVALID_RESPONSE, 'Gemini returned empty content.');
        }

        return [
            'text' => $text,
            'raw' => $json,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractText(array $payload): string
    {
        $candidates = $payload['candidates'] ?? null;
        if (! is_array($candidates) || $candidates === []) {
            return '';
        }

        $first = $candidates[0] ?? null;
        if (! is_array($first)) {
            return '';
        }

        $parts = $first['content']['parts'] ?? null;
        if (! is_array($parts)) {
            return '';
        }

        $chunks = [];
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $chunks[] = $part['text'];
            }
        }

        return trim(implode('', $chunks));
    }

    private function failure(string $reason, string $message, ?Throwable $previous = null): GeminiException
    {
        return new GeminiException($reason, $message, 0, $previous);
    }

    /**
     * SSL verify option for Guzzle/cURL.
     * Uses bundled CA on Windows when php.ini lacks curl.cainfo.
     */
    private function sslVerifyOption(): bool|string
    {
        $configured = config('ai.gemini.ssl_verify');

        if ($configured === false || $configured === 'false' || $configured === '0') {
            return app()->environment('local') ? false : true;
        }

        if ($configured === true || $configured === 'true' || $configured === '1') {
            return true;
        }

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $bundle = config('ai.gemini.ca_bundle');
        if (is_string($bundle) && $bundle !== '' && is_file($bundle)) {
            return $bundle;
        }

        return true;
    }
}
