<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiResponse;
use App\Support\AiResponseText;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenRouter adapter using its OpenAI-compatible Chat Completions API.
 * It remains inside AcadSyntra's provider registry/router/fallback chain.
 */
class OpenRouterProvider extends ExternalProvider
{
    public function name(): string { return 'openrouter'; }

    protected function hasCredentials(): bool
    {
        return trim((string) ($this->config['api_key'] ?? '')) !== '';
    }

    protected function endpoint(): ?string
    {
        $base = rtrim((string) ($this->config['base_url'] ?? 'https://openrouter.ai/api/v1'), '/');
        if ($base === '') return null;
        return preg_match('~/chat/completions$~i', $base) ? $base : $base.'/chat/completions';
    }

    protected function headers(): array
    {
        return parent::headers() + array_filter([
            'Authorization' => 'Bearer '.(string) ($this->config['api_key'] ?? ''),
            'HTTP-Referer' => trim((string) ($this->config['site_url'] ?? config('app.url'))),
            'X-Title' => trim((string) ($this->config['app_name'] ?? config('app.name', 'AcadSyntra'))),
        ]);
    }

    protected function buildPrompt(string $feature, array $payload): string
    {
        return json_encode(['feature'=>$feature,'context'=>$payload], JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
    }

    protected function parseResponse(string $feature, array $raw, float $time, float $cost): AiResponse
    {
        if ($providerError = $this->providerError($raw)) {
            return new AiResponse(
                source: $this->name(), feature: $feature, success: false,
                summary: $providerError['message'], processingTime: $time, cost: $cost,
                provider: $this->name(), model: $this->model(), errorCode: $providerError['code'], confidence: 0.0,
                metadata: ['provider_error' => $providerError['type']],
            );
        }

        $content = $this->extractAssistantContent($raw);
        if ($content === '') {
            $finishReason = trim((string) data_get($raw, 'choices.0.finish_reason', data_get($raw, 'choices.0.finishReason', '')));
            $refusal = $this->normalizeContent(data_get($raw, 'choices.0.message.refusal', ''));

            if ($refusal !== '' || in_array(strtolower($finishReason), ['content_filter', 'safety', 'refusal'], true)) {
                return new AiResponse(
                    source: $this->name(), feature: $feature, success: false,
                    summary: 'The AI provider could not return an answer for this request.',
                    processingTime: $time, cost: $cost, provider: $this->name(), model: $this->model(),
                    errorCode: 'AI_RESPONSE_BLOCKED', confidence: 0.0,
                );
            }

            $diagnostic = array_filter([
                'choice_count' => is_array($raw['choices'] ?? null) ? count($raw['choices']) : 0,
                'finish_reason' => $finishReason ?: null,
                'raw_keys' => array_keys($raw),
            ]);

            return new AiResponse(
                source: $this->name(), feature: $feature, success: false,
                summary: 'The AI provider returned an empty response. Ask an administrator to verify the configured OpenRouter model and fallback provider in AI Settings.',
                processingTime: $time, cost: $cost, provider: $this->name(), model: $this->model(),
                errorCode: 'AI_EMPTY_RESPONSE', confidence: 0.0, metadata: ['empty_response_diagnostic' => $diagnostic],
            );
        }

        $data = json_decode($content, true);
        if (is_array($data) && isset($data['error']) && is_array($data['error'])) {
            $providerError = $this->providerError($data) ?? ['code' => 'AI_PROVIDER_UNAVAILABLE', 'message' => 'The AI provider returned an error object.', 'type' => 'provider_error'];
            return new AiResponse(
                source: $this->name(), feature: $feature, success: false,
                summary: $providerError['message'], processingTime: $time, cost: $cost,
                provider: $this->name(), model: $this->model(), errorCode: $providerError['code'], confidence: 0.0,
                metadata: ['provider_error' => $providerError['type']],
            );
        }
        if (! is_array($data)) {
            if ($this->requiresStructuredJson($feature)) {
                return new AiResponse(
                    source: $this->name(), feature: $feature, success: false,
                    summary: 'The AI provider response could not be interpreted safely.',
                    processingTime: $time, cost: $cost, provider: $this->name(), model: $this->model(),
                    errorCode: 'AI_RESPONSE_PARSE_FAILED', confidence: 0.0,
                );
            }

            $data = ['raw' => $content, 'answer' => $content, 'summary' => $content];
        }
        $data = $this->normalizeAssistantData($data, $content);
        return new AiResponse(
            source:$this->name(),feature:$feature,success:true,data:$data['data']??$data,summary:$data['summary']??null,
            score:isset($data['score'])?(float)$data['score']:null,issues:is_array($data['issues']??null)?$data['issues']:[],
            processingTime:$time,cost:$cost,provider:$this->name(),model:$this->model(),
        );
    }

    private function extractAssistantContent(array $raw): string
    {
        $choices = (array) ($raw['choices'] ?? []);
        foreach ($choices as $choice) {
            if (! is_array($choice)) continue;

            foreach ([
                'message.content',
                'delta.content',
                'text',
                'message.assistant',
                'assistant',
                'message.tool_calls',
                'tool_calls',
                'message.reasoning',
                'message.reasoning_content',
                'message.content_parts',
            ] as $path) {
                $content = $this->normalizeContent(data_get($choice, $path, ''));
                if ($content !== '') return $content;
            }
        }

        foreach (['output_text', 'content', 'message', 'response'] as $path) {
            $content = $this->normalizeContent(data_get($raw, $path, ''));
            if ($content !== '') return $content;
        }

        return '';
    }

    private function requiresStructuredJson(string $feature): bool
    {
        return $feature !== '__health_check' && in_array('structured_output', (array) config('ai.feature_capabilities.'.$feature, []), true);
    }

    /** @param array<string,mixed> $data */
    private function normalizeAssistantData(array $data, string $rawContent): array
    {
        $target = is_array($data['data'] ?? null) ? $data['data'] : $data;
        if ($this->normalizeContent($target['answer'] ?? '') !== '') {
            return $data;
        }

        $answer = $this->synthesizeAnswer($target);
        if ($answer === '') {
            // Some models return a structured JSON draft with no `answer` field.
            // Flatten it into readable prose instead of showing raw JSON.
            $answer = trim((string) (AiResponseText::humanizeJson($rawContent) ?? ''));
        }
        if ($answer === '') {
            $answer = trim($rawContent);
        }
        if ($answer === '') {
            return $data;
        }

        if (isset($data['data']) && is_array($data['data'])) {
            $data['data']['answer'] = $answer;
        } else {
            $data['answer'] = $answer;
        }
        $data['summary'] = $this->normalizeContent($data['summary'] ?? '') ?: mb_substr($answer, 0, 500);

        return $data;
    }

    /** @param array<string,mixed> $data */
    private function synthesizeAnswer(array $data): string
    {
        $preferred = [
            'message', 'explanation', 'response', 'content', 'text', 'summary',
            'recommendation', 'uncertainty_details', 'example', 'topic',
        ];

        $lines = [];
        foreach ($preferred as $key) {
            if (! array_key_exists($key, $data)) continue;
            $value = $this->normalizeContent($data[$key]);
            if ($value === '') continue;
            $label = str_replace('_', ' ', ucfirst($key));
            $lines[] = in_array($key, ['message', 'explanation', 'response', 'content', 'text', 'summary'], true)
                ? $value
                : $label.': '.$value;
        }

        foreach (['self_check_questions', 'suggested_actions', 'next_steps', 'questions', 'items'] as $key) {
            if (! array_key_exists($key, $data) || ! is_array($data[$key])) continue;
            $items = collect($data[$key])->map(fn (mixed $item): string => $this->normalizeContent($item))->filter()->values();
            if ($items->isNotEmpty()) {
                $lines[] = str_replace('_', ' ', ucfirst($key)).":\n- ".$items->implode("\n- ");
            }
        }

        return trim(implode("\n\n", array_values(array_unique($lines))));
    }

    private function normalizeContent(mixed $content): string
    {
        if (is_string($content)) return trim($content);

        if (is_array($content)) {
            if (! array_is_list($content)) {
                foreach (['text', 'content', 'value', 'output_text', 'message', 'arguments'] as $key) {
                    if (array_key_exists($key, $content)) {
                        $normalized = $this->normalizeContent($content[$key]);
                        if ($normalized !== '') return $normalized;
                    }
                }

                if (isset($content['function']) && is_array($content['function'])) {
                    $normalized = $this->normalizeContent($content['function']['arguments'] ?? $content['function']);
                    if ($normalized !== '') return $normalized;
                }
            }

            $parts = collect($content)->map(function (mixed $part): string {
                return $this->normalizeContent($part);
            })->filter(fn (string $part): bool => trim($part) !== '' && ! in_array(trim($part), ['text', 'output_text'], true))->implode("\n");

            return trim($parts);
        }

        return '';
    }

    /** @return array{code:string,message:string,type:string}|null */
    private function providerError(array $raw): ?array
    {
        $error = $raw['error'] ?? null;
        if (! is_array($error)) return null;

        $message = trim((string) ($error['message'] ?? $error['error'] ?? 'The AI provider returned an error.'));
        $type = strtolower(trim((string) ($error['type'] ?? $error['code'] ?? 'provider_error')));
        $code = match (true) {
            str_contains($type, 'auth') || str_contains($type, 'key') => 'AI_PROVIDER_AUTH_FAILED',
            str_contains($type, 'rate') || str_contains($message, 'rate limit') => 'AI_PROVIDER_RATE_LIMITED',
            str_contains($type, 'model') || str_contains($message, 'model') || str_contains($message, 'not found') => 'AI_MODEL_NOT_FOUND',
            str_contains($type, 'context') || str_contains($message, 'context') => 'AI_INVALID_PROVIDER_RESPONSE',
            default => 'AI_PROVIDER_UNAVAILABLE',
        };

        return [
            'code' => $code,
            'message' => $message !== '' ? $message : 'The AI provider returned an error.',
            'type' => $type !== '' ? $type : 'provider_error',
        ];
    }

    protected function estimateCostMicroUsd(array $raw): int
    {
        $reported = data_get($raw, 'usage.cost');
        if (is_numeric($reported)) {
            return max(0, Money::toMinorRounded((string) $reported, 6));
        }

        return parent::estimateCostMicroUsd($raw);
    }

    /** @return list<array{id:string,name:string,context_length:int|null,pricing:array}> */
    public function discoverModels(): array
    {
        if(!$this->hasCredentials()) return [];
        $base=rtrim((string)($this->config['base_url']??'https://openrouter.ai/api/v1'),'/');
        if(preg_match('~/chat/completions$~i',$base)) $base=preg_replace('~/chat/completions$~i','',$base);
        try {
            $response=Http::acceptJson()->withToken((string)$this->config['api_key'])
                ->connectTimeout(max(1, min(3, (int) ($this->config['health_check_connect_timeout'] ?? $this->config['connect_timeout'] ?? 3))))
                ->timeout(max(3, min(8, (int) ($this->config['health_check_timeout'] ?? $this->config['request_timeout'] ?? 6))))
                ->get($base.'/models');
        } catch (\Throwable $exception) {
            Log::warning('OpenRouter model catalog refresh failed', ['exception'=>$exception::class]);
            return [];
        }
        if(!$response->successful()) {
            Log::warning('OpenRouter model catalog refresh was rejected', ['http_status'=>$response->status()]);
            return [];
        }
        return collect((array)$response->json('data',[]))->filter(fn($m)=>is_array($m)&&!empty($m['id']))->map(fn($m)=>[
            'id'=>(string)$m['id'],'name'=>(string)($m['name']??$m['id']),'context_length'=>isset($m['context_length'])?(int)$m['context_length']:null,
            'pricing'=>is_array($m['pricing']??null)?$m['pricing']:[],
        ])->values()->all();
    }
}
