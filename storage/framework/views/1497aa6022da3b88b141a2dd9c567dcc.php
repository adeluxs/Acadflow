<?php $__env->startSection('title', auth()->user()->isSuperAdmin() ? 'System Settings' : 'Institution Settings'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold"><?php echo e(auth()->user()->isSuperAdmin() ? 'System Settings' : 'Institution Settings'); ?></h1>
            <p class="text-sm text-gray-500 mt-1"><?php echo e(auth()->user()->isSuperAdmin() ? 'Manage platform-wide defaults and infrastructure controls.' : 'Manage settings for your institution. Platform-only controls remain protected.'); ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
            <?php if(auth()->user()->hasPermission(\App\Enums\Permission::MANAGE_AI_SETTINGS)): ?>
                <a href="<?php echo e(route('ai.settings')); ?>" class="acad-primary-button inline-flex items-center rounded-lg px-3 py-2 text-xs font-semibold">AI Settings</a>
            <?php endif; ?>
            <?php if(auth()->user()->isSuperAdmin() && Route::has('admin.monetization')): ?>
                <a href="<?php echo e(route('admin.monetization')); ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Monetization</a>
            <?php elseif(auth()->user()->isUniversityAdmin() && Route::has('institution.commercial-account')): ?>
                <a href="<?php echo e(route('institution.commercial-account')); ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Commercial Account</a>
            <?php endif; ?>
            <?php if(auth()->user()->isSuperAdmin()): ?>
                <a href="<?php echo e(route('admin.settings.features')); ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Feature & Module Management</a>
                <a href="<?php echo e(route('admin.settings.permissions')); ?>" class="acad-link">Permission Management</a>
                <a href="<?php echo e(route('admin.settings.audit-logs')); ?>" class="acad-link">Audit Logs</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if(session('success')): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?>

    <?php if($errors->any()): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <ul>
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if(auth()->user()->isSuperAdmin()): ?>
    <!-- Assignment type availability (platform-owner controlled) -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm mb-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-black text-slate-900">Assignment types</h2>
                <p class="mt-1 text-xs text-slate-500">Optional assignment types can be enabled or disabled. Disabling a type hides it from assignment creation and blocks new assignments of that type; existing records are kept.</p>
            </div>
            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold <?php echo e($siwesAssignmentEnabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'); ?>">
                SIWES <?php echo e($siwesAssignmentEnabled ? 'enabled' : 'disabled'); ?>

            </span>
        </div>
        <form method="POST" action="<?php echo e(route('admin.settings.assignment-types.siwes')); ?>" class="mt-4 flex flex-wrap items-center gap-3">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>
            <input type="hidden" name="enabled" value="0">
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="enabled" value="1" <?php if($siwesAssignmentEnabled): echo 'checked'; endif; ?> class="rounded border-slate-300">
                Enable the SIWES assignment type
            </label>
            <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">Save</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Settings Navigation Tabs -->
    <div class="mb-6">
        <div class="flex flex-wrap gap-2">
            <?php $__currentLoopData = $settingGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupKey => $groupInfo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="#<?php echo e($groupKey); ?>" 
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                          <?php echo e($loop->first ? 'text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'); ?>"
                   <?php if($loop->first): ?> style="background-color: var(--acad-primary)" <?php endif; ?>">
                    <?php echo e($groupInfo['name']); ?>

                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

    <form method="POST" action="<?php echo e(route('admin.settings.update')); ?>">
        <?php echo csrf_field(); ?>

        <!-- General Settings -->
        <?php if(isset($settings['general'])): ?>
        <div id="general" class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-bold">General Settings</h2>
                <p class="text-sm text-gray-600">Platform name, branding, timezone, and basic configuration</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php $__currentLoopData = $settings['general']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $setting): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <?php echo e(\Str::title(str_replace('_', ' ', $setting->key))); ?>

                            </label>
                            <?php echo $__env->make('settings.partials.field', ['setting' => $setting], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            <?php if($setting->description): ?>
                                <p class="text-xs text-gray-500 mt-1"><?php echo e($setting->description); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Academic Settings -->
        <?php if(isset($settings['academic'])): ?>
        <div id="academic" class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-bold">Academic Settings</h2>
                <p class="text-sm text-gray-600">Semesters, submission rules, grading policies</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php $__currentLoopData = $settings['academic']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $setting): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <?php echo e(\Str::title(str_replace('_', ' ', $setting->key))); ?>

                            </label>
                            <?php echo $__env->make('settings.partials.field', ['setting' => $setting], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            <?php if($setting->description): ?>
                                <p class="text-xs text-gray-500 mt-1"><?php echo e($setting->description); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Notification Settings -->
        <?php if(isset($settings['notification'])): ?>
        <div id="notification" class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-bold">Notification Settings</h2>
                <p class="text-sm text-gray-600">Channels, templates, reminders, announcements</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php $__currentLoopData = $settings['notification']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $setting): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <?php echo e(\Str::title(str_replace('_', ' ', $setting->key))); ?>

                            </label>
                            <?php echo $__env->make('settings.partials.field', ['setting' => $setting], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            <?php if($setting->description): ?>
                                <p class="text-xs text-gray-500 mt-1"><?php echo e($setting->description); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Security Settings -->
        <?php if(isset($settings['security'])): ?>
        <?php
            $securityLabels = [
                'email_verification_enabled' => 'Email Verification Enabled',
                'password_min_length' => 'Minimum password length',
                'password_require_uppercase' => 'Require uppercase letter',
                'password_require_numbers' => 'Require number',
                'password_require_special' => 'Require special character',
                'max_login_attempts' => 'Failed login attempts before lockout',
                'lockout_duration_minutes' => 'Login lockout duration (minutes)',
                'login_requests_per_minute' => 'Login requests per minute',
                'registration_requests_per_hour' => 'Registration requests per hour',
                'password_reset_requests_per_minute' => 'Password reset requests per minute',
                'verification_requests_per_minute' => 'Verification requests per minute',
                'two_factor_attempts_per_minute' => 'Two-factor attempts per minute',
                'payment_requests_per_minute' => 'Payment requests per minute',
            ];
            $securitySections = [
                'Email verification' => [
                    'description' => 'Require email confirmation before workspace access. Disabling this requirement keeps actual verification records unchanged. Only Super Admin can change it.',
                    'keys' => ['email_verification_enabled'],
                ],
                'Password policy' => [
                    'description' => 'These are the exact password rules used by registration and password-change/reset validation.',
                    'keys' => ['password_min_length','password_require_uppercase','password_require_numbers','password_require_special'],
                ],
                'Login protection' => [
                    'description' => 'Failed-attempt lockout and request throttling for sign-in.',
                    'keys' => ['max_login_attempts','lockout_duration_minutes','login_requests_per_minute'],
                ],
                'Security request limits' => [
                    'description' => 'Existing sensitive actions use these central limits. Users receive the real remaining retry time when a limit is reached.',
                    'keys' => ['registration_requests_per_hour','password_reset_requests_per_minute','verification_requests_per_minute','two_factor_attempts_per_minute','payment_requests_per_minute'],
                ],
                'Sessions & account security' => [
                    'description' => 'Existing session, two-factor and audit controls.',
                    'keys' => ['session_timeout_minutes','enable_two_factor','max_concurrent_sessions','enable_audit_logs','audit_log_retention_days'],
                ],
            ];
        ?>
        <div id="security" class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-bold">Security Settings</h2>
                <p class="text-sm text-gray-600">One source of truth for password policy, login protection and existing authentication rate limits.</p>
            </div>
            <div class="space-y-8 p-6">
                <?php $__currentLoopData = $securitySections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sectionName => $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $sectionSettings = $settings['security']->filter(fn ($item) => in_array($item->key, $section['keys'], true));
                    ?>
                    <?php if($sectionSettings->isNotEmpty()): ?>
                        <section>
                            <div class="mb-4">
                                <h3 class="font-bold text-slate-900"><?php echo e($sectionName); ?></h3>
                                <p class="mt-1 text-sm text-slate-500"><?php echo e($section['description']); ?></p>
                            </div>
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <?php $__currentLoopData = $sectionSettings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $setting): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <div>
                                        <label class="mb-2 block text-sm font-bold text-gray-700">
                                            <?php echo e($securityLabels[$setting->key] ?? \Str::title(str_replace('_', ' ', $setting->key))); ?>

                                        </label>
                                        <?php echo $__env->make('settings.partials.field', ['setting' => $setting], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                                        <?php if($setting->description): ?>
                                            <p class="mt-1 text-xs text-gray-500"><?php echo e($setting->description); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </section>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- PWA Settings -->
        <?php if(isset($settings['pwa'])): ?>
        <div id="pwa" class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-bold">PWA Settings</h2>
                <p class="text-sm text-gray-600">Offline mode, caching, sync behavior</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php $__currentLoopData = $settings['pwa']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $setting): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <?php echo e(\Str::title(str_replace('_', ' ', $setting->key))); ?>

                            </label>
                            <?php echo $__env->make('settings.partials.field', ['setting' => $setting], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            <?php if($setting->description): ?>
                                <p class="text-xs text-gray-500 mt-1"><?php echo e($setting->description); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Storage Settings -->
        <?php if(isset($settings['storage'])): ?>
        <div id="storage" class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-bold">Storage Settings</h2>
                <p class="text-sm text-gray-600">File uploads, retention, archives</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php $__currentLoopData = $settings['storage']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $setting): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <?php echo e(\Str::title(str_replace('_', ' ', $setting->key))); ?>

                            </label>
                            <?php echo $__env->make('settings.partials.field', ['setting' => $setting], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            <?php if($setting->description): ?>
                                <p class="text-xs text-gray-500 mt-1"><?php echo e($setting->description); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="flex justify-end">
            <button type="submit" class="acad-primary-button px-6 py-3 rounded-lg font-medium">
                Save All Settings
            </button>
        </div>
    </form>

    <?php if(auth()->user()->isSuperAdmin()): ?>
    <div class="mb-6 overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-r from-indigo-50 via-white to-violet-50 shadow-sm">
        <div class="flex flex-col gap-4 p-6 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">Release control</p>
                <h2 class="mt-1 text-lg font-bold text-slate-950">Feature & Module Management</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-600">Runtime availability is controlled from one centralized page. Configuration such as AI provider keys or notification channel preferences remains in its specialist settings area.</p>
            </div>
            <a href="<?php echo e(route('admin.settings.features')); ?>" class="acad-primary-button inline-flex shrink-0 items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold">Manage feature states →</a>
        </div>
    </div>

    <!-- Payment Gateway Settings -->
    <div id="payment-gateways" class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <div>
                <h2 class="text-lg font-bold">Payment Gateways</h2>
                <p class="text-sm text-gray-600">Configure payment providers for wallet funding, marketplace purchases, refunds and other supported transactions</p>
            </div>
            <a href="<?php echo e(route('admin.payment-gateways.create')); ?>" 
               class="acad-primary-button px-4 py-2 rounded text-sm">
               + Add Gateway
            </a>
        </div>
        <div class="p-6">
            <?php if($paymentGateways->isEmpty()): ?>
                <p class="text-gray-500 text-center py-8">No payment gateways configured. Add one to start accepting payments.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php $__currentLoopData = $paymentGateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gateway): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="border rounded-lg p-4 <?php echo e($gateway->is_active ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200'); ?>">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-bold text-gray-900"><?php echo e($gateway->name); ?></h3>
                                    <p class="text-xs text-gray-500"><?php echo e($gateway->description ?? $gateway->code); ?></p>
                                </div>
                                <?php if($gateway->is_test_mode): ?>
                                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 text-xs rounded">Test</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="space-y-2 mb-4">
                                <div class="flex items-center text-sm">
                                    <span class="w-20 text-gray-500">Status:</span>
                                    <span class="px-2 py-0.5 rounded text-xs <?php echo e($gateway->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <?php echo e($gateway->is_active ? 'Active' : 'Inactive'); ?>

                                    </span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="w-20 text-gray-500">Configured:</span>
                                    <span class="text-sm <?php echo e($gateway->credentials ? 'text-green-600' : 'text-red-600'); ?>">
                                        <?php echo e($gateway->credentials ? '✓' : '✗'); ?>

                                    </span>
                                </div>
                                <?php if($gateway->transactions_count > 0): ?>
                                    <div class="flex items-center text-sm">
                                        <span class="w-20 text-gray-500">Transactions:</span>
                                        <span class="text-sm text-gray-700"><?php echo e($gateway->transactions_count); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex gap-2">
                                <a href="<?php echo e(route('admin.payment-gateways.edit', $gateway)); ?>" 
                                   class="flex-1 text-center px-3 py-1.5 bg-white border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50">
                                    Edit
                                </a>
                                <?php if(!$gateway->is_active): ?>
                                    <form method="POST" action="<?php echo e(route('admin.payment-gateways.destroy', $gateway)); ?>" 
                                          onsubmit="return confirm('Delete this gateway?')">
                                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="px-3 py-1.5 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200">
                                            Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mb-6 overflow-hidden rounded-2xl border border-emerald-100 bg-emerald-50/60 shadow-sm">
        <div class="flex flex-col gap-4 p-6 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">Commercial controls</p>
                <h2 class="mt-1 text-lg font-bold text-slate-950">Subscriptions have been retired</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-600"><?php echo e(auth()->user()->isSuperAdmin() ? 'Pricing, wallets, commissions, AI usage, withdrawals and institutional commercial accounts are managed from the centralized Monetization Center.' : 'Commercial pricing is controlled by Platform Admin. You can view your institution’s approved budget, sponsored access and commercial terms, then request changes when needed.'); ?></p>
            </div>
            <?php if(auth()->user()->isSuperAdmin()): ?>
                <a href="<?php echo e(route('admin.monetization')); ?>" class="acad-primary-button inline-flex shrink-0 items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold">Open Monetization →</a>
            <?php elseif(auth()->user()->isUniversityAdmin()): ?>
                <a href="<?php echo e(route('institution.commercial-account')); ?>" class="acad-primary-button inline-flex shrink-0 items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold">View Commercial Account →</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/settings/index.blade.php ENDPATH**/ ?>