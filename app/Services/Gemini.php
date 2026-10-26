<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A small client for the Gemini API (generateContent), called with Laravel's HTTP client.
 * The API key stays on the server; it is read from services.gemini.key.
 */
class Gemini
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * Sends one request and returns the answer text and the web pages Google Search grounded it on.
     * Null means the request failed, was blocked, or returned no text; the reason is logged.
     *
     * @param  list<array<string, mixed>>  $parts  Text and inline_data parts, in order.
     * @return array{text: string, sources: list<array{title: string, url: string}>}|null
     */
    public function generate(string $model, array $parts, bool $googleSearch = false, bool $json = false): ?array
    {
        $body = ['contents' => [['role' => 'user', 'parts' => $parts]]];
        // Google Search grounding can't be combined with a JSON response type on Gemini 2.5, so search
        // answers are asked to reply in JSON by the prompt instead.
        if ($googleSearch) {
            $body['tools'] = [['google_search' => (object) []]];
        }
        if ($json) {
            $body['generationConfig'] = ['responseMimeType' => 'application/json'];
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                ->acceptJson()->timeout(90)
                ->post(sprintf(self::ENDPOINT, rawurlencode($model)), $body);
        } catch (ConnectionException $exception) {
            Log::warning('Gemini request could not connect.', ['model' => $model, 'message' => $exception->getMessage()]);

            return null;
        }
        if (! $response->successful()) {
            Log::warning('Gemini request failed.', ['model' => $model, 'status' => $response->status(), 'error' => $response->json('error.message')]);

            return null;
        }

        $text = collect((array) $response->json('candidates.0.content.parts', []))
            ->map(fn ($part): string => is_array($part) ? (string) ($part['text'] ?? '') : '')
            ->implode('');
        if (trim($text) === '') {
            Log::warning('Gemini returned no text.', ['model' => $model, 'finish' => $response->json('candidates.0.finishReason'), 'block' => $response->json('promptFeedback.blockReason')]);

            return null;
        }

        $sources = collect((array) $response->json('candidates.0.groundingMetadata.groundingChunks', []))
            ->map(fn ($chunk): array => [
                'title' => mb_substr(trim((string) data_get($chunk, 'web.title', '')), 0, 120),
                'url' => (string) data_get($chunk, 'web.uri', ''),
            ])
            ->filter(fn (array $source): bool => str_starts_with($source['url'], 'https://'))
            ->unique('url')->take(5)->values()->all();

        return ['text' => $text, 'sources' => $sources];
    }

    /**
     * The first JSON object in a model answer, which may be wrapped in a ```json fence or prose.
     *
     * @return array<string, mixed>|null
     */
    public static function decodeJson(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }
        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }
}
