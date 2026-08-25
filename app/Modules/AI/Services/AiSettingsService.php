<?php

namespace App\Modules\AI\Services;

use App\Modules\Settings\Services\SystemSettingsService;

/**
 * Runtime AI settings: DB overrides (system_settings.metadata.ai) + env defaults.
 * GEMINI_API_KEY always comes from .env only.
 */
class AiSettingsService
{
    public function __construct(
        private readonly SystemSettingsService $systemSettingsService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = $this->stored();

        return [
            'enabled' => $this->enabled(),
            'provider' => $this->provider(),
            'model' => $this->model(),
            'base_url' => $this->baseUrl(),
            'timeout' => $this->timeout(),
            'max_output_tokens' => $this->maxOutputTokens(),
            'temperature' => $this->temperature(),
            'max_offers_for_recommendation' => $this->maxOffersForRecommendation(),
            'max_conversation_turns' => $this->maxConversationTurns(),
            'timezone' => $this->timezone(),
            'default_currency' => $this->defaultCurrency(),
            'prefer_rules_nlu' => $this->preferRulesNlu(),
            'api_key_configured' => $this->isConfigured(),
            'updated_at' => $stored['updated_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stored(): array
    {
        $metadata = $this->systemSettingsService->current()->metadata ?? [];

        return is_array($metadata['ai'] ?? null) ? $metadata['ai'] : [];
    }

    public function enabled(): bool
    {
        if (array_key_exists('enabled', $this->stored())) {
            return (bool) $this->stored()['enabled'];
        }

        return filter_var(config('ai.enabled', true), FILTER_VALIDATE_BOOL);
    }

    public function provider(): string
    {
        $value = trim((string) ($this->stored()['provider'] ?? config('ai.provider', 'gemini')));

        return $value !== '' ? $value : 'gemini';
    }

    public function model(): string
    {
        $value = trim((string) ($this->stored()['model'] ?? config('ai.gemini.model', 'gemini-flash-lite-latest')));

        return $value !== '' ? $value : 'gemini-flash-lite-latest';
    }

    public function baseUrl(): string
    {
        $value = trim((string) ($this->stored()['base_url'] ?? config('ai.gemini.base_url')));

        return rtrim($value !== '' ? $value : 'https://generativelanguage.googleapis.com/v1beta', '/');
    }

    public function timeout(): int
    {
        return max(3, (int) ($this->stored()['timeout'] ?? config('ai.gemini.timeout', 12)));
    }

    public function maxOutputTokens(): int
    {
        return max(128, (int) ($this->stored()['max_output_tokens'] ?? config('ai.gemini.max_output_tokens', 1024)));
    }

    public function temperature(): float
    {
        $value = $this->stored()['temperature'] ?? config('ai.gemini.temperature', 0.2);

        return max(0.0, min(2.0, (float) $value));
    }

    public function maxOffersForRecommendation(): int
    {
        return max(1, (int) ($this->stored()['max_offers_for_recommendation'] ?? config('ai.gemini.max_offers_for_recommendation', 8)));
    }

    public function maxConversationTurns(): int
    {
        return max(0, (int) ($this->stored()['max_conversation_turns'] ?? config('ai.gemini.max_conversation_turns', 6)));
    }

    public function timezone(): string
    {
        $value = trim((string) ($this->stored()['timezone'] ?? config('ai.timezone', 'Africa/Tripoli')));

        return $value !== '' ? $value : 'Africa/Tripoli';
    }

    public function defaultCurrency(): string
    {
        $value = strtoupper(trim((string) ($this->stored()['default_currency'] ?? config('ai.default_currency', 'LYD'))));

        return $value !== '' ? $value : 'LYD';
    }

    public function preferRulesNlu(): bool
    {
        if (array_key_exists('prefer_rules_nlu', $this->stored())) {
            return (bool) $this->stored()['prefer_rules_nlu'];
        }

        return true;
    }

    public function apiKey(): string
    {
        return trim((string) config('ai.gemini.api_key', ''));
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function integrationStatus(): array
    {
        $configured = $this->isConfigured();
        $enabled = $this->enabled();

        $mode = match (true) {
            ! $configured => app()->environment('production') ? 'missing' : 'missing',
            ! $enabled => 'disabled',
            default => 'configured',
        };

        if (! $configured && ! app()->environment('production')) {
            $mode = 'missing';
        }

        return [
            'provider' => $this->provider(),
            'model' => $this->model(),
            'enabled' => $enabled,
            'configured' => $configured,
            'healthy' => $configured && $enabled,
            'mode' => $mode,
            'api_key_hint' => $configured ? '••••••••' : null,
            'env_key' => 'GEMINI_API_KEY',
        ];
    }

    /**
     * @return list<string>
     */
    public function availableModels(): array
    {
        return [
            'gemini-flash-lite-latest',
            'gemini-flash-latest',
            'gemini-3.1-flash-lite-preview',
            'gemini-3-flash-preview',
            'gemini-2.5-flash-lite',
            'gemini-2.5-flash',
            'gemini-2.5-pro',
        ];
    }

    public function defaultModel(): string
    {
        return 'gemini-flash-lite-latest';
    }
}
