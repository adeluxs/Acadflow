<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SubscriptionService
{
    /**
     * Check if user has access to a feature
     */
    public function hasFeature(User $user, string $feature): bool
    {
        return $user->hasFeature($feature);
    }

    /**
     * Check if user can upload more materials (storage quota)
     */
    public function canUploadMaterial(User $user, int $fileSizeBytes): bool
    {
        $subscription = $user->activeSubscription()->first();
        if (! $subscription || ! $subscription->plan) {
            return false;
        }

        $plan = $subscription->plan;

        // Check file size against plan limit (max_file_upload_size_mb field)
        $maxFileSize = $plan->max_file_upload_size_mb ?? config('app.default_max_file_size_mb', 50);
        if ($fileSizeBytes > ($maxFileSize * 1024 * 1024)) {
            return false;
        }

        // Check storage quota
        $maxStorageGB = $plan->max_storage_gb;
        if ($maxStorageGB) {
            $currentUsage = $this->getStorageUsage($user);
            $newUsage = $currentUsage + ($fileSizeBytes / (1024 * 1024 * 1024)); // Convert to GB
            if ($newUsage > $maxStorageGB) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user has reached course limit
     */
    public function canCreateCourse(User $user): bool
    {
        $subscription = $user->activeSubscription()->first();
        if (! $subscription || ! $subscription->plan) {
            return false;
        }

        $maxCourses = $subscription->plan->max_courses;
        if (! $maxCourses) {
            return true; // Unlimited
        }

        $currentCount = $user->enrollments()
            ->where('status', 'enrolled')
            ->distinct('course_id')
            ->count('course_id');

        return $currentCount < $maxCourses;
    }

    /**
     * Get current storage usage for a user (in GB)
     */
    public function getStorageUsage(User $user): float
    {
        return Cache::remember(
            "user:{$user->id}:storage_usage",
            now()->addMinutes(30),
            function () use ($user) {
                $totalBytes = $user->enrollments()
                    ->join('course_materials', 'courses.id', '=', 'course_materials.course_id')
                    ->sum('course_materials.file_size');

                return ($totalBytes ?? 0) / (1024 * 1024 * 1024); // Convert to GB
            }
        );
    }

    /**
     * Get remaining storage for user (in GB)
     */
    public function getRemainingStorage(User $user): ?float
    {
        $subscription = $user->activeSubscription()->first();
        if (! $subscription || ! $subscription->plan) {
            return 0;
        }

        $maxStorage = $subscription->plan->max_storage_gb;
        if (! $maxStorage) {
            return null; // Unlimited
        }

        $used = $this->getStorageUsage($user);

        return max(0, $maxStorage - $used);
    }

    /**
     * Get effective file upload limit for a user (considers plan)
     * Returns size in bytes
     */
    public function getUploadLimitForUser(User $user): int
    {
        $subscription = $user->activeSubscription()->first();
        if (! $subscription || ! $subscription->plan) {
            return SettingService::getMaxUploadSize(); // Fallback to global setting
        }

        $plan = $subscription->plan;
        $maxMB = $plan->max_file_upload_size_mb ?? 50;
        return $maxMB * 1024 * 1024;
    }

    /**
     * Get subscription summary for user
     */
    public function getSubscriptionSummary(User $user): array
    {
        $subscription = $user->activeSubscription()->first();
        if (! $subscription || ! $subscription->plan) {
            return [
                'plan' => null,
                'features' => [],
                'limits' => [
                    'max_courses' => 0,
                    'max_storage_gb' => 0,
                    'max_file_size_mb' => 0,
                ],
                'usage' => [
                    'courses' => 0,
                    'storage_gb' => 0,
                ],
                'subscription' => null,
            ];
        }

        $plan = $subscription->plan;

        return [
            'plan' => [
                'name' => $plan->name,
                'display_name' => $plan->display_name,
                'is_active' => $plan->is_active,
            ],
            'features' => $plan->features ?? [],
            'limits' => [
                'max_courses' => $plan->max_courses,
                'max_storage_gb' => $plan->max_storage_gb,
                'max_file_size_mb' => $plan->max_file_size_mb,
                'max_file_upload_size_mb' => $plan->max_file_upload_size_mb,
            ],
            'usage' => [
                'courses' => $this->getCurrentCourseCount($user),
                'storage_gb' => round($this->getStorageUsage($user), 2),
            ],
            'remaining' => [
                'courses' => $plan->max_courses ? max(0, $plan->max_courses - $this->getCurrentCourseCount($user)) : null,
                'storage_gb' => $plan->max_storage_gb ? max(0, round($plan->max_storage_gb - $this->getStorageUsage($user), 2)) : null,
            ],
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'is_active' => $subscription->isActive(),
                'amount' => $subscription->amount,
                'currency' => $subscription->currency,
                'billing_cycle' => $subscription->billing_cycle,
                'payment_status' => $subscription->payment_status,
                'started_at' => $subscription->started_at,
                'ends_at' => $subscription->ends_at,
            ],
        ];
    }

    /**
     * Get current enrolled course count for user
     */
    protected function getCurrentCourseCount(User $user): int
    {
        return $user->enrollments()
            ->where('status', 'enrolled')
            ->distinct('course_id')
            ->count('course_id');
    }

    /**
     * Validate subscription before material upload
     * Uses SubscriptionPlan limits (per-plan limits) NOT global settings
     */
    public function validateMaterialUpload(User $user, int $fileSize, string $fileType): array
    {
        $errors = [];

        // Lecturers and admins can always upload materials regardless of subscription
        if ($user->isLecturer() || $user->isAdmin()) {
            // Still validate file type and size against reasonable defaults
            $allowedExtensions = SettingService::getAllowedExtensions();
            $fileExtension = strtolower($fileType);
            // Extract extension from mime type or get actual extension
            if (strpos($fileExtension, '/') !== false) {
                // For mime types like 'application/pdf', we need to check differently
                // Use the actual file extension from the upload instead
                return $errors; // Skip extension check here, it's done in validation
            }
            
            if (! in_array($fileExtension, $allowedExtensions)) {
                $errors[] = 'File type not allowed. Allowed: '.implode(', ', $allowedExtensions);
            }
            
            return $errors;
        }

        $subscription = $user->activeSubscription()->first();
        if (! $subscription || ! $subscription->plan) {
            $errors[] = 'No active subscription found. Please upgrade your plan to upload materials.';
            return $errors;
        }

        $plan = $subscription->plan;

        // Check file size against plan limit (max_file_upload_size_mb)
        $maxFileSizeMB = $plan->max_file_upload_size_mb ?? 50;
        if ($fileSize > ($maxFileSizeMB * 1024 * 1024)) {
            $errors[] = "File size exceeds your plan's limit of {$maxFileSizeMB}MB.";
        }

        // Check file type against global allowed extensions
        $allowedExtensions = SettingService::getAllowedExtensions();
        $extension = strtolower(pathinfo($fileType, PATHINFO_EXTENSION));
        if (! in_array($extension, $allowedExtensions)) {
            $errors[] = 'File type not allowed. Allowed: '.implode(', ', $allowedExtensions);
        }

        // Check storage quota
        $maxStorageGB = $plan->max_storage_gb;
        if ($maxStorageGB) {
            $currentUsage = $this->getStorageUsage($user);
            $newUsage = $currentUsage + ($fileSize / (1024 * 1024 * 1024));
            if ($newUsage > $maxStorageGB) {
                $errors[] = "This upload would exceed your storage limit of {$maxStorageGB}GB. Current usage: ".round($currentUsage, 2).'GB.';
            }
        }

        // Check if plan is active
        if (! $plan->is_active) {
            $errors[] = 'Your subscription plan is inactive. Please contact support.';
        }

        return $errors;
    }
}
