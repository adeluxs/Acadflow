<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\SubmissionVersion;
use Illuminate\Support\Facades\Auth;

class ConflictResolver
{
    /**
     * Check if there's a conflict between local and server versions
     */
    public static function checkConflict(Submission $submission, array $localData): ?array
    {
        $serverVersion = $submission->versions()->where('is_current', true)->first();
        
        if (! $serverVersion) {
            return null; // No conflict, server has no current version
        }

        $conflicts = [];

        // Check for file conflicts
        if (isset($localData['files'])) {
            $serverFiles = $serverVersion->file_name ?? 'unknown';
            $localFiles = implode(', ', array_column($localData['files'], 'name'));
            
            if ($serverFiles !== $localFiles) {
                $conflicts['files'] = [
                    'server' => $serverFiles,
                    'local' => $localFiles,
                ];
            }
        }

        // Check for content conflicts
        if (isset($localData['submission_notes'])) {
            $serverNotes = $submission->notes;
            if ($serverNotes !== $localData['submission_notes']) {
                $conflicts['notes'] = [
                    'server' => $serverNotes,
                    'local' => $localData['submission_notes'],
                ];
            }
        }

        // Check timestamps
        if ($serverVersion->updated_at && isset($localData['timestamp'])) {
            $serverTime = $serverVersion->updated_at->timestamp;
            if ($localData['timestamp'] < $serverTime) {
                $conflicts['timestamp'] = [
                    'server' => $serverVersion->updated_at->toISOString(),
                    'local' => date('c', $localData['timestamp']),
                ];
            }
        }

        return empty($conflicts) ? null : [
            'submission_id' => $submission->id,
            'conflicts' => $conflicts,
            'server_version' => $serverVersion->version,
            'local_version' => $localData['version'] ?? null,
        ];
    }

    /**
     * Resolve conflict by choosing server version
     */
    public static function resolveUseServer(Submission $submission): Submission
    {
        // Keep server version, discard local changes
        return $submission;
    }

    /**
     * Resolve conflict by choosing local version
     */
    public static function resolveUseLocal(Submission $submission, array $localData): Submission
    {
        // Update submission with local data
        $submission->update([
            'notes' => $localData['submission_notes'] ?? $submission->notes,
        ]);

        // Create new version if files provided
        if (isset($localData['files'])) {
            $submission->versions()->where('is_current', true)->update(['is_current' => false]);

            foreach ($localData['files'] as $fileData) {
                $submission->versions()->create([
                    'file_name' => $fileData['name'],
                    'file_path' => $fileData['path'] ?? null,
                    'file_size' => $fileData['size'] ?? null,
                    'is_current' => true,
                    'version' => $submission->versions()->count() + 1,
                ]);
            }
        }

        return $submission;
    }

    /**
     * Resolve conflict by merging (if possible)
     */
    public static function resolveMerge(Submission $submission, array $localData): Submission
    {
        // For submissions, merging typically means keeping both versions
        // and letting the lecturer decide
        
        $submission->update([
            'notes' => ($submission->notes ? $submission->notes . "\n\n[Offline edit]: " : '') . 
                     ($localData['submission_notes'] ?? ''),
        ]);

        return $submission;
    }
}
