<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    protected array $allowedMimeTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'image/jpeg',
        'image/png',
        'image/gif',
    ];

    protected int $maxFileSize = 51200; // 50MB in KB

    public function upload(UploadedFile $file, string $directory, ?string $customName = null): array
    {
        $this->validateFile($file);

        $filename = $customName ?? $this->generateFilename($file);
        $path = $file->storeAs($directory, $filename);

        return [
            'path' => $path,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ];
    }

    public function uploadMultiple(array $files, string $directory): array
    {
        $uploadedFiles = [];

        foreach ($files as $file) {
            $uploadedFiles[] = $this->upload($file, $directory);
        }

        return $uploadedFiles;
    }

    public function delete(string $path): bool
    {
        return Storage::delete($path);
    }

    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $basename = Str::slug(Str::beforeLast($file->getClientOriginalName(), '.'));

        return "{$basename}_{$this->generateUniqueId()}.{$extension}";
    }

    protected function generateUniqueId(): string
    {
        return Str::random(12);
    }

    protected function validateFile(UploadedFile $file): void
    {
        if (! in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw new \InvalidArgumentException(
                'File type not allowed. Allowed types: PDF, DOC, DOCX, ZIP, JPG, PNG, GIF'
            );
        }

        if ($file->getSize() > $this->maxFileSize * 1024) {
            throw new \InvalidArgumentException(
                "File size exceeds maximum allowed size of {$this->maxFileSize}KB"
            );
        }
    }

    public function getFileUrl(string $path): string
    {
        return Storage::url($path);
    }

    public function getFileContents(string $path): string
    {
        return Storage::get($path);
    }
}
