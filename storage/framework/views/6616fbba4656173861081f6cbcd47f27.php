<?php $__env->startSection('title', 'Notification Management'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Notification Management</h1>
            <p class="text-gray-600 mt-2">Control notification channels and monitor delivery</p>
        </div>
        <div class="flex gap-2">
            <a href="<?php echo e(route('admin.notifications.announce')); ?>" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Send Announcement
            </a>
            <form method="POST" action="<?php echo e(route('admin.notifications.retry-failed')); ?>">
                <?php echo csrf_field(); ?>
                <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700">
                    Retry Failed
                </button>
            </form>
        </div>
    </div>

    <section class="mb-6 rounded-xl border bg-white p-6">
        <h2 class="text-xl font-bold">Browser push delivery</h2>
        <p class="mt-2 text-sm"><?php echo e($pushConfigured ? 'Delivery keys are configured.' : 'Delivery keys have not been configured.'); ?> Enable the Push Notifications module and channel, then enroll a browser from Notification Preferences.</p>
        <?php if(!$pushConfigured && auth()->user()->isSuperAdmin()): ?>
        <form method="POST" action="<?php echo e(route('admin.notifications.push-keys')); ?>" class="mt-4 flex flex-wrap gap-3">
            <?php echo csrf_field(); ?>
            <label class="text-sm">Delivery contact <input required name="subject" placeholder="mailto:support@yourdomain.com" class="rounded-lg border-gray-300"></label>
            <button class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Generate delivery keys</button>
        </form>
        <?php endif; ?>
        <?php if($errors->any()): ?><p role="alert" class="mt-3 text-red-700"><?php echo e($errors->first()); ?></p><?php endif; ?>
    </section>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase">Total (30d)</p>
            <p class="text-2xl font-bold text-gray-900"><?php echo e($stats['total']); ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase">Successful</p>
            <p class="text-2xl font-bold text-green-600"><?php echo e($stats['success']); ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase">Failed</p>
            <p class="text-2xl font-bold text-red-600"><?php echo e($stats['failed']); ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase">Success Rate</p>
            <p class="text-2xl font-bold text-gray-900">
                <?php echo e($stats['total'] > 0 ? round(($stats['success'] / $stats['total']) * 100, 1) : 0); ?>%
            </p>
        </div>
    </div>

    <!-- Channel Settings -->
    <div class="bg-white rounded-lg shadow p-8 mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Global Channel Settings</h2>
        <div class="mb-6 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
            <p class="font-semibold">Availability and delivery configuration are separate.</p>
            <p class="mt-1 text-blue-800">These switches configure notification delivery channels after the feature is available. The platform-wide release state for Notifications and Push Notifications is controlled centrally by the Super Admin in Feature &amp; Module Management.</p>
            <?php if(auth()->user()?->role === 'super_admin' && Route::has('admin.settings.features')): ?>
                <a href="<?php echo e(route('admin.settings.features')); ?>" class="mt-2 inline-flex font-semibold text-blue-700 hover:text-blue-900 hover:underline">Open Feature &amp; Module Management →</a>
            <?php endif; ?>
        </div>
        <p class="text-sm text-gray-600 mb-6">Enable or disable notification delivery channels system-wide. Individual users can still adjust their own preferences.</p>

        <form action="<?php echo e(route('admin.notifications.update-channels')); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>
            <div class="space-y-4">
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">In-App Notifications</span>
                        <p class="text-sm text-gray-600">Database/website notifications</p>
                    </div>
                    <input type="hidden" name="in_app_notifications_enabled" value="0">
                    <input type="checkbox" name="in_app_notifications_enabled" value="1"
                           <?php echo e($channels['in_app_notifications_enabled'] == '1' ? 'checked' : ''); ?>

                           class="h-5 w-5 text-blue-600 rounded">
                </label>
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Email Notifications</span>
                        <p class="text-sm text-gray-600">Email delivery for alerts</p>
                    </div>
                    <input type="hidden" name="email_notifications_enabled" value="0">
                    <input type="checkbox" name="email_notifications_enabled" value="1"
                           <?php echo e($channels['email_notifications_enabled'] == '1' ? 'checked' : ''); ?>

                           class="h-5 w-5 text-blue-600 rounded">
                </label>
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Push Notifications</span>
                        <p class="text-sm text-gray-600">Browser push notifications</p>
                    </div>
                    <input type="hidden" name="push_notifications_enabled" value="0">
                    <input type="checkbox" name="push_notifications_enabled" value="1"
                           <?php echo e($channels['push_notifications_enabled'] == '1' ? 'checked' : ''); ?>

                           class="h-5 w-5 text-blue-600 rounded">
                </label>
            </div>
            <div class="pt-6 border-t mt-6">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-semibold">
                    Save Channel Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Channel Breakdown -->
    <?php if(count($stats['by_channel']) > 0): ?>
    <div class="bg-white rounded-lg shadow p-8 mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Delivery by Channel (30d)</h2>
        <div class="grid grid-cols-3 gap-4">
            <?php $__currentLoopData = $stats['by_channel']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $channel => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="bg-gray-50 rounded p-4">
                    <p class="text-sm text-gray-600 uppercase"><?php echo e(ucfirst($channel)); ?></p>
                    <p class="text-xl font-bold text-gray-900"><?php echo e($count); ?></p>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Failures -->
    <?php if($recentFailures->count() > 0): ?>
    <div class="bg-white rounded-lg shadow p-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Recent Failures</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-2 text-left">User</th>
                        <th class="px-4 py-2 text-left">Channel</th>
                        <th class="px-4 py-2 text-left">Title</th>
                        <th class="px-4 py-2 text-left">Attempts</th>
                        <th class="px-4 py-2 text-left">Last Attempt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $recentFailures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="border-b">
                            <td class="px-4 py-2"><?php echo e($log->user?->email ?? 'Unknown'); ?></td>
                            <td class="px-4 py-2"><?php echo e(ucfirst($log->channel)); ?></td>
                            <td class="px-4 py-2"><?php echo e($log->notification?->title ?? 'N/A'); ?></td>
                            <td class="px-4 py-2"><?php echo e($log->attempt_count); ?></td>
                            <td class="px-4 py-2 text-gray-600"><?php echo e($log->attempted_at?->diffForHumans() ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/admin/notifications/index.blade.php ENDPATH**/ ?>