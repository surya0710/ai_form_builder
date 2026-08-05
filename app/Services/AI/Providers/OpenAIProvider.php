<?php

namespace App\Services\AI\Providers;

use App\Exceptions\AI\AIProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements AIProviderInterface
{
    /**
     * @return array{content: string, tokens: int|null, model: string}
     *
     * @throws AIProviderException
     */
    public function generate(string $prompt): array
    {
        $config = config('ai.providers.openai');
        $apiKey = $config['api_key'] ?? null;

        if (empty($apiKey)) {
            throw AIProviderException::missingApiKey();
        }

        $model = $config['model'] ?? 'gpt-4o-mini';
        $baseUrl = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $url = $baseUrl.'/chat/completions';

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) ($config['timeout'] ?? 30))
                ->retry(
                    (int) ($config['retries'] ?? 2),
                    (int) ($config['retry_delay'] ?? 1000),
                    function ($exception): bool {
                        if ($exception instanceof ConnectionException) {
                            return true;
                        }

                        if ($exception instanceof RequestException) {
                            return in_array($exception->response?->status(), [408, 429, 500, 502, 503, 504], true);
                        }

                        return false;
                    },
                )
                ->post($url, [
                    'model' => $model,
                    'temperature' => (float) ($config['temperature'] ?? 0.2),
                    'max_tokens' => (int) ($config['max_tokens'] ?? 2000),
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('OpenAI connection failure', ['message' => $e->getMessage()]);

            throw AIProviderException::timeout($e);
        } catch (RequestException $e) {
            $this->throwForFailedResponse(
                $e->response?->status() ?? 502,
                $e->response?->body() ?? $e->getMessage(),
            );
        }

        if ($response->failed()) {
            $this->throwForFailedResponse($response->status(), $response->body());
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';

        return [
            'content' => $content,
            'tokens' => $data['usage']['total_tokens'] ?? null,
            'model' => $data['model'] ?? $model,
        ];
    }

    protected function throwForFailedResponse(int $status, string $body): never
    {
        Log::warning('OpenAI provider error', [
            'status' => $status,
            'body' => $body,
        ]);

        match (true) {
            $status === 401, $status === 403 => throw AIProviderException::unauthorized($body),
            $status === 429 => throw AIProviderException::rateLimited($body),
            $status === 408, $status === 504 => throw AIProviderException::timeout(),
            default => throw AIProviderException::unavailable($body),
        };
    }
}
