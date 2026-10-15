<?php

namespace App\Services\Media;

use App\Contracts\Media\MalwareScannerInterface;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaSecurityService
{
    public function __construct(private readonly MalwareScannerInterface $scanner) {}

    public function store(UploadedFile $file, User $owner, ?Model $attachable = null, string $visibility = 'private', array $metadata = []): MediaAsset
    {
        $this->validateUpload($file);

        $temporaryPath = $file->getRealPath();
        if (! is_string($temporaryPath) || $temporaryPath === '' || ! is_file($temporaryPath)) {
            throw ValidationException::withMessages(['file' => 'The uploaded file could not be inspected safely.']);
        }

        $scanResult = $this->scanner->scan($temporaryPath);
        $this->assertSafeScanResult($scanResult);
        $sha256 = hash_file('sha256', $temporaryPath) ?: null;

        $disk = (string) SettingService::get('storage_provider', config('filesystems.default', 'local'));
        if (! array_key_exists($disk, config('filesystems.disks', []))) {
            $disk = (string) config('filesystems.default', 'local');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $directory = 'media/'.($owner->university_id ?: 'global').'/'.$owner->id.'/'.now()->format('Y/m');
        $filename = Str::uuid().($extension !== '' ? '.'.$extension : '');
        $path = $file->storeAs($directory, $filename, $disk);

        if (! $path) {
            throw ValidationException::withMessages(['file' => 'The file could not be stored.']);
        }

        // Do not create a database record unless the configured adapter can
        // immediately see the object it just stored. This prevents orphaned
        // media rows that later fail during preview/download.
        try {
            if (! Storage::disk($disk)->exists($path)) {
                throw ValidationException::withMessages(['file' => 'The file was written but could not be verified in storage. Please try again.']);
            }
        } catch (ValidationException $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw ValidationException::withMessages(['file' => 'The storage provider could not verify the uploaded file. Please try again.']);
        }

        try {
            return MediaAsset::create([
                'university_id' => $owner->university_id,
                'owner_id' => $owner->id,
                'attachable_type' => $attachable?->getMorphClass(),
                'attachable_id' => $attachable?->getKey(),
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'extension' => $extension,
                'size_bytes' => $file->getSize() ?: 0,
                'sha256' => $sha256,
                'visibility' => $visibility,
                'scan_status' => $scanResult['status'] ?? 'error',
                'scan_provider' => $scanResult['provider'] ?? class_basename($this->scanner),
                'scan_result' => $scanResult['details'] ?? [],
                'preview_status' => $this->previewStatus($file->getMimeType() ?: ''),
                'preview_metadata' => $this->previewMetadata($file),
                'metadata' => $metadata,
                'scanned_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    public function scan(MediaAsset $asset): MediaAsset
    {
        [$absolutePath, $cleanup] = $this->inspectionPath($asset);

        try {
            $result = $this->scanner->scan($absolutePath);
        } finally {
            $cleanup();
        }

        $status = $result['status'] ?? 'error';
        $asset->update([
            'scan_status' => $status,
            'scan_provider' => $result['provider'] ?? class_basename($this->scanner),
            'scan_result' => $result['details'] ?? [],
            'scanned_at' => now(),
            'quarantined_at' => $status === 'infected' ? now() : null,
        ]);

        if ($status === 'infected') {
            $quarantine = 'quarantine/'.$asset->uuid.'/'.basename($asset->path);
            Storage::disk($asset->disk)->move($asset->path, $quarantine);
            $asset->update(['path' => $quarantine, 'visibility' => 'private']);
        }

        return $asset->fresh();
    }

    public function canPreview(MediaAsset $asset): bool
    {
        return in_array($asset->scan_status, ['clean', 'skipped'], true)
            && $asset->preview_status === 'available';
    }

    private function assertSafeScanResult(array $result): void
    {
        $status = (string) ($result['status'] ?? 'error');

        if ($status === 'infected') {
            throw ValidationException::withMessages(['file' => 'The uploaded file was rejected by the malware scanner.']);
        }

        if ($status === 'error' && (bool) config('media.scan_fail_closed', true)) {
            throw ValidationException::withMessages(['file' => 'The file scanner is unavailable. Please try again or contact an administrator.']);
        }

        if (! in_array($status, ['clean', 'skipped', 'error'], true)) {
            throw ValidationException::withMessages(['file' => 'The file scanner returned an unsupported result.']);
        }
    }

    /** @return array{0:string,1:callable():void} */
    private function inspectionPath(MediaAsset $asset): array
    {
        $disk = Storage::disk($asset->disk);

        try {
            $path = $disk->path($asset->path);
            if (is_file($path)) {
                return [$path, static function (): void {}];
            }
        } catch (\Throwable) {
            // Remote adapters do not expose local filesystem paths.
        }

        $stream = $disk->readStream($asset->path);
        if (! is_resource($stream)) {
            throw ValidationException::withMessages(['file' => 'The stored file could not be opened for security inspection.']);
        }

        $temporary = tempnam(sys_get_temp_dir(), 'acadflow-scan-');
        if (! is_string($temporary)) {
            fclose($stream);
            throw ValidationException::withMessages(['file' => 'A secure temporary scan file could not be created.']);
        }

        $target = fopen($temporary, 'wb');
        if (! is_resource($target)) {
            fclose($stream);
            @unlink($temporary);
            throw ValidationException::withMessages(['file' => 'The stored file could not be prepared for security inspection.']);
        }

        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        return [$temporary, static function () use ($temporary): void { @unlink($temporary); }];
    }

    private function validateUpload(UploadedFile $file): void
    {
        $maxBytes = SettingService::getMaxUploadSize();
        $allowed = array_map('strtolower', SettingService::getAllowedExtensions());
        $extension = strtolower($file->getClientOriginalExtension());

        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'The uploaded file is invalid.']);
        }

        if (($file->getSize() ?: 0) > $maxBytes) {
            throw ValidationException::withMessages(['file' => 'The file exceeds the configured upload limit.']);
        }

        if ($extension === '' || ! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages(['file' => 'This file type is not allowed.']);
        }

        $dangerous = ['php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'bat', 'cmd', 'exe', 'dll', 'js', 'html', 'htm', 'svg'];
        if (in_array($extension, $dangerous, true)) {
            throw ValidationException::withMessages(['file' => 'Executable or active-content files are not accepted.']);
        }
    }

    private function previewStatus(string $mime): string
    {
        return str_starts_with($mime, 'image/')
            || str_starts_with($mime, 'audio/')
            || str_starts_with($mime, 'video/')
            || $mime === 'application/pdf'
            ? 'available'
            : 'unsupported';
    }

    private function previewMetadata(UploadedFile $file): array
    {
        return [
            'mime' => $file->getMimeType(),
            'inline' => $this->previewStatus($file->getMimeType() ?: '') === 'available',
            'original_extension' => strtolower($file->getClientOriginalExtension()),
        ];
    }
}
