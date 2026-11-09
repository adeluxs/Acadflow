<?php

namespace App\Jobs;

use App\Http\Controllers\AiDiscoveryController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Tells Bing, Yandex and other IndexNow engines that a page is new or changed. */
class PingIndexNow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;
    public int $timeout = 20;

    /** @param list<string> $urls */
    public function __construct(public array $urls) {}

    public function handle(): void
    {
        if (! app()->environment('production') || $this->urls === []) {
            return;
        }
        $host = parse_url(config('app.url'), PHP_URL_HOST);
        $key = AiDiscoveryController::indexNowKey();
        try {
            Http::timeout(15)->acceptJson()->post('https://api.indexnow.org/indexnow', [
                'host' => $host, 'key' => $key, 'keyLocation' => url($key.'.txt'), 'urlList' => array_values(array_slice($this->urls, 0, 1000)),
            ]);
        } catch (\Throwable $e) {
            Log::info('IndexNow ping failed.', ['error' => $e->getMessage()]);
        }
    }
}
