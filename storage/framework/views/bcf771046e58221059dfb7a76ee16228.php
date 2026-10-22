<?php $__env->startSection('title', 'Notification Settings'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Notification Settings</h1>
        <p class="text-gray-600 mt-2">Control how you receive notifications</p>
    </div>

    <section data-browser-push class="mb-6 rounded-xl border bg-white p-6">
        <h2 class="text-xl font-bold">Notifications on this browser</h2>
        <p data-push-status role="status" aria-live="polite" class="mt-2 text-sm text-gray-600">Checking browser notification availability…</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" data-push-enable disabled class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white disabled:opacity-50">Enable this browser</button>
            <button type="button" data-push-disable disabled class="rounded-lg border px-4 py-2 disabled:opacity-50">Disable this browser</button>
            <button type="button" data-push-test disabled class="rounded-lg border px-4 py-2 disabled:opacity-50">Send test notification</button>
        </div>
        <p class="mt-3 text-xs text-gray-500">Keep Push Notifications enabled in your preferences below. Browser permission and system notification settings also control what appears.</p>
    </section>

    <form action="<?php echo e(route('notifications.settings.update')); ?>" method="POST" class="bg-white rounded-lg shadow p-8">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>

        <!-- Global Channel Toggles -->
        <div class="mb-8">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Notification Channels</h3>
            <div class="space-y-4">
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Email Notifications</span>
                        <p class="text-sm text-gray-600">Receive notifications via email</p>
                    </div>
                    <input type="hidden" name="email_enabled" value="0">
                    <input type="checkbox" name="email_enabled" value="1" 
                           <?php echo e($settings->email_enabled ? 'checked' : ''); ?>

                           class="h-5 w-5 text-blue-600 rounded">
                </label>
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Push Notifications</span>
                        <p class="text-sm text-gray-600">Receive browser push notifications</p>
                    </div>
                    <input type="hidden" name="push_enabled" value="0">
                    <input type="checkbox" name="push_enabled" value="1" 
                           <?php echo e($settings->push_enabled ? 'checked' : ''); ?>

                           class="h-5 w-5 text-blue-600 rounded">
                </label>
            </div>
        </div>

        <!-- Per-Type Toggles -->
        <div class="mb-8">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Notification Types</h3>
            <p class="text-sm text-gray-600 mb-4">Choose which types of notifications you want to receive.</p>
            <div class="space-y-4">
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Submission Notifications</span>
                        <p class="text-sm text-gray-600">New submissions, corrections, approvals</p>
                    </div>
                    <input type="hidden" name="submission_notifications" value="0">
                    <input type="checkbox" name="submission_notifications" value="1" 
                           <?php echo e($settings->submission_notifications ? 'checked' : ''); ?>

                           class="h-5 w-5 text-blue-600 rounded">
                </label>
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Grade Notifications</span>
                        <p class="text-sm text-gray-600">When grades are posted</p>
                    </div>
                    <input type="hidden" name="grade_notifications" value="0">
                    <input type="checkbox" name="grade_notifications" value="1" 
                           <?php echo e($settings->grade_notifications ? 'checked' : ''); ?>

                           class="h-5 w-5 text-blue-600 rounded">
                </label>
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Attendance Notifications</span>
                        <p class="text-sm text-gray-600">Attendance session alerts</p>
                    </div>
                    <input type="hidden" name="attendance_notifications" value="0">
                    <input type="checkbox" name="attendance_notifications" value="1" 
                           <?php echo e($settings->attendance_notifications ? 'checked' : ''); ?>

                           class="h-5 w-5 text-blue-600 rounded">
                </label>
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Billing Notifications</span>
                        <p class="text-sm text-gray-600">Payment confirmations, overdue alerts</p>
                    </div>
                    <input type="hidden" name="billing_notifications" value="0">
                    <input type="checkbox" name="billing_notifications" value="1" 
                           <?php echo e($settings->billing_notifications ? 'checked' : ''); ?>

                           class="h-5 w-5 text-blue-600 rounded">
                </label>
            </div>
        </div>

        <section class="mb-8 space-y-4"><h3 class="text-xl font-bold">Your workspace & reminder preferences</h3>
        <?php $__currentLoopData = ['in_app_enabled'=>['In-app notification centre','Show alerts inside AcadSyntra.'],'academic_alerts'=>['Academic alerts','Assignments, courses and academic activities.'],'organisation_updates'=>['Department and institution messages','Membership, verification and affiliation updates.'],'research_updates'=>['Research Studio updates','Supervision, research invitations and milestones.'],'onboarding_reminders'=>['Onboarding reminders','Continue your setup; never more than one reminder every 12 hours.'],'engagement_reminders'=>['Optional engagement reminders','Opt in to occasional reminders about useful unfinished work.'],'email_open_tracking'=>['Optional email open measurement','Allow a tracking image in notification emails. Opens may be affected by email privacy proxies.']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$content): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <label class="flex items-center justify-between gap-5"><div><span class="font-semibold"><?php echo e($content[0]); ?></span><p class="mt-1 text-sm text-slate-500"><?php echo e($content[1]); ?></p></div><input type="hidden" name="<?php echo e($key); ?>" value="0"><input type="checkbox" name="<?php echo e($key); ?>" value="1" <?php if($settings->$key): echo 'checked'; endif; ?> class="h-5 w-5 rounded text-indigo-600"></label>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </section>
        <section class="mb-8"><h3 class="text-xl font-bold">Quiet hours</h3><p class="mt-2 text-sm text-slate-500">Email and push wait until quiet hours end in your time zone. In-app messages remain available. Account security emails keep their separate security workflow.</p><div class="mt-4 grid gap-4 sm:grid-cols-3"><label class="text-sm">Time zone<select name="timezone" class="mt-1 w-full rounded-xl border-slate-300"><?php $__currentLoopData = timezone_identifiers_list(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $timezone): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($timezone); ?>" <?php if($settings->timezone===$timezone): echo 'selected'; endif; ?>><?php echo e($timezone); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label><label class="text-sm">Start<input name="quiet_hours_start" type="time" value="<?php echo e($settings->quiet_hours_start?substr($settings->quiet_hours_start,0,5):''); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label><label class="text-sm">End<input name="quiet_hours_end" type="time" value="<?php echo e($settings->quiet_hours_end?substr($settings->quiet_hours_end,0,5):''); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label></div></section>
        <div class="flex gap-3 pt-6 border-t">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                Save Settings
            </button>
        </div>
    </form>
    <section class="mt-6 rounded-2xl border bg-white p-6"><h2 class="text-xl font-bold">Enrolled devices</h2><p class="mt-2 text-sm text-slate-500">Remove a device without changing your other devices. Endpoints and encryption keys are never shown.</p><?php $__empty_1 = true; $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"><div class="min-w-0"><p class="max-w-xl break-words text-sm font-semibold"><?php echo e($device->user_agent?:'Browser device'); ?></p><p class="mt-1 text-xs text-slate-500">Added <?php echo e($device->created_at->format('d M Y')); ?> · <?php echo e($device->expires_at?->isPast()?'Expired':'Active'); ?></p></div><form method="POST" action="<?php echo e(route('browser-push.device.remove',$device)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="acad-secondary-button">Remove device</button></form></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="mt-4 text-sm text-slate-500">No devices enrolled. Enable this browser above.</p><?php endif; ?><div class="mt-4"><?php echo e($devices->links()); ?></div><p class="mt-4 text-xs text-slate-500">On iPhone/iPad, open AcadSyntra from its Home Screen icon before enabling notifications. Browser and OS support determines delivery while the site is closed.</p></section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/notifications/settings.blade.php ENDPATH**/ ?>