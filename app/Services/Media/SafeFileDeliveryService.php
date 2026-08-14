<?php

namespace App\Services\Media;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SafeFileDeliveryService
{
    /**
     * Stream a stored file without requiring Flysystem file-size metadata.
     *
     * Some storage adapters can read a file but cannot reliably return size
     * metadata. Laravel's FilesystemAdapter::download()/response() requests
     * file_size internally, which can turn a valid preview into a 500 error.
     * This method deliberately streams the resource and omits Content-Length.
     */
    public function stream(
        string $diskName,
        string $path,
        string $fileName,
        ?string $mimeType = null,
        string $disposition = 'attachment',
        array $headers = []
    ): StreamedResponse {
        $diskName = trim($diskName) !== '' ? $diskName : (string) config('filesystems.default', 'local');
        $path = ltrim(trim($path), '/');

        abort_if($path === '', 404, 'The requested file is unavailable.');
        abort_unless(array_key_exists($diskName, (array) config('filesystems.disks', [])), 404, 'The configured storage location is unavailable.');

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);

        try {
            if (! $disk->exists($path)) {
                Log::warning('Stored file record points to a missing object.', [
                    'disk' => $diskName,
                    'path' => $path,
                ]);
                abort(404, 'This file is no longer available. Please upload it again or contact an administrator.');
            }

            $stream = $disk->readStream($path);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::warning('Stored file could not be opened for streaming.', [
                'disk' => $diskName,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
            abort(503, 'The file storage service could not open this file. Please try again.');
        }

        if (! is_resource($stream)) {
            Log::warning('Storage adapter returned an invalid read stream.', [
                'disk' => $diskName,
                'path' => $path,
            ]);
            abort(404, 'This file is no longer available.');
        }

        $safeName = trim(str_replace(["\r", "\n", '"'], '', $fileName));
        if ($safeName === '') {
            $safeName = basename($path) ?: 'download';
        }

        $disposition = $disposition === 'inline' ? 'inline' : 'attachment';
        $responseHeaders = array_merge([
            'Content-Type' => $mimeType ?: 'application/octet-stream',
            'Content-Disposition' => $disposition.'; filename="'.$safeName.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ], $headers);

        return response()->stream(static function () use ($stream): void {
            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);
                    if ($chunk === false) {
                        break;
                    }
                    echo $chunk;
                    if (function_exists('flush')) {
                        flush();
                    }
                }
            } finally {
                fclose($stream);
            }
        }, 200, $responseHeaders);
    }
}
