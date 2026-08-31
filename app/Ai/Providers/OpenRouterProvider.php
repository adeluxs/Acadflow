<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiResponse;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenRouter adapter using its OpenAI-compatible Chat Completions API.
 * It remains inside AcadFlow's provider registry/router/fallback chain.
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
            'X-Title' => trim((string) ($this->config['app_name'] ?? config('app.name', 'AcadFlow'))),
        ]);
    }

    protected function buildPrompt(string $feature, array $payload): string
    {
        return json_encode(['feature'=>$feature,'context'=>$payload], JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
    }

    protected function parseResponse(string $feature, array $raw, float $time, float $cost): AiResponse
    {
        $content=(string)data_get($raw,'choices.0.message.content','');
        $data=json_decode($content,true); if(!is_array($data))$data=['raw'=>$content];
        return new AiResponse(
            source:$this->name(),feature:$feature,success:true,data:$data['data']??$data,summary:$data['summary']??null,
            score:isset($data['score'])?(float)$data['score']:null,issues:is_array($data['issues']??null)?$data['issues']:[],
            processingTime:$time,cost:$cost,provider:$this->name(),model:$this->model(),
        );
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
                ->connectTimeout(max(1,(int)($this->config['connect_timeout']??10)))
                ->timeout(min(30,max(5,(int)($this->config['request_timeout']??20))))
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
