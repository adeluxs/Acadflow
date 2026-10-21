<?php $__env->startSection('title', 'My Profile'); ?>
<?php $__env->startSection('page-title', 'My Profile'); ?>
<?php $__env->startSection('page-subtitle', 'Your identity, institution membership and academic access'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $toneClasses = [
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
    $membershipClass = $toneClasses[$membershipStatus['tone']] ?? $toneClasses['slate'];
?>

<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <?php if(session('success')): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    
    <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-indigo-950 to-blue-800 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-[1.5rem] bg-white text-3xl font-black text-indigo-700 shadow-lg">
                <?php if($user->avatar_media_id && $user->avatarMedia): ?>
                    <img src="<?php echo e(route('media.preview', $user->avatarMedia)); ?>" alt="<?php echo e($user->full_name); ?> profile photo" class="h-full w-full object-cover">
                <?php elseif($user->avatar): ?>
                    <img src="<?php echo e(asset('storage/'.$user->avatar)); ?>" alt="<?php echo e($user->full_name); ?> profile photo" class="h-full w-full object-cover">
                <?php else: ?>
                    <?php echo e(strtoupper(substr($user->first_name ?: 'U', 0, 1))); ?>

                <?php endif; ?>
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-black tracking-tight sm:text-3xl"><?php echo e($user->full_name); ?></h1>
                <p class="mt-1 text-sm text-indigo-100">
                    <?php if($user->username): ?>&#64;<?php echo e($user->username); ?> · <?php endif; ?><?php echo e($user->email); ?>

                </p>
                <div class="mt-4 flex flex-wrap gap-2 text-[11px] font-bold">
                    <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/15"><?php echo e(\Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $user->role))); ?></span>
                    <?php if($user->account_type): ?>
                        <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/15"><?php echo e(\Illuminate\Support\Str::headline(str_replace('_', ' ', $user->account_type))); ?></span>
                    <?php endif; ?>
                    <span class="rounded-full px-3 py-1 ring-1 <?php echo e($membershipStatus['is_member'] ? 'bg-emerald-400/20 ring-emerald-300/30' : 'bg-white/10 ring-white/15'); ?>"><?php echo e($membershipStatus['label']); ?></span>
                    <span class="rounded-full px-3 py-1 ring-1 <?php echo e($user->is_active ? 'bg-emerald-400/20 ring-emerald-300/30' : 'bg-rose-400/20 ring-rose-300/30'); ?>"><?php echo e($user->is_active ? 'Active account' : 'Inactive account'); ?></span>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <main class="space-y-6">
            
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-black text-slate-950">Institution & membership</h2>
                    <span class="rounded-full px-3 py-1 text-[11px] font-black ring-1 <?php echo e($membershipClass); ?>"><?php echo e($membershipStatus['label']); ?></span>
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-[11px] font-black uppercase tracking-wide text-slate-400">Institution</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-800"><?php echo e($university?->name ?? 'Independent / not attached'); ?></dd>
                        <?php if($university?->code): ?><dd class="text-xs text-slate-500"><?php echo e($university->short_name ?: $university->name); ?> · <?php echo e($university->code); ?></dd><?php endif; ?>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-[11px] font-black uppercase tracking-wide text-slate-400">Faculty</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-800"><?php echo e($faculty?->name ?? '—'); ?></dd>
                        <?php if($faculty?->code): ?><dd class="text-xs text-slate-500"><?php echo e($faculty->code); ?></dd><?php endif; ?>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-[11px] font-black uppercase tracking-wide text-slate-400">Department</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-800"><?php echo e($department?->name ?? '—'); ?></dd>
                        <?php if($department?->code): ?><dd class="text-xs text-slate-500"><?php echo e($department->code); ?></dd><?php endif; ?>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-[11px] font-black uppercase tracking-wide text-slate-400">Membership status</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-800">
                            <?php if($affiliation): ?>
                                <?php echo e(\Illuminate\Support\Str::headline($affiliation->status)); ?>

                            <?php elseif($membershipStatus['is_member']): ?>
                                Institution set (affiliation not recorded)
                            <?php else: ?>
                                Independent
                            <?php endif; ?>
                        </dd>
                        <dd class="mt-1 text-xs text-slate-500"><?php echo e($membershipStatus['detail']); ?></dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-[11px] font-black uppercase tracking-wide text-slate-400">Academic session</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-800"><?php echo e($academicSession?->name ?? 'No active session'); ?></dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-[11px] font-black uppercase tracking-wide text-slate-400">Active semester</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-800"><?php echo e($activeSemester?->name ?? '—'); ?></dd>
                    </div>
                </dl>
            </section>

            
            <?php if($user->isStudent()): ?>
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-black text-slate-950">Course enrollment</h2>
                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-[11px] font-bold text-indigo-700"><?php echo e($activeEnrollments->count()); ?> active</span>
                    </div>

                    <div class="mt-4 space-y-2">
                        <?php $__empty_0 = true; $__currentLoopData = $activeEnrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enrollment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                            <a href="<?php echo e(route('courses.show', $enrollment->course)); ?>" class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 p-4 hover:border-indigo-200 hover:bg-indigo-50/30">
                                <div class="min-w-0">
                                    <p class="text-xs font-black uppercase tracking-wide text-indigo-600"><?php echo e($enrollment->course?->code); ?></p>
                                    <p class="truncate font-bold text-slate-900"><?php echo e($enrollment->course?->name); ?></p>
                                    <p class="text-xs text-slate-500"><?php echo e($enrollment->course?->access_mode === 'independent' ? 'Independent classroom' : ($enrollment->course?->department?->name ?? 'Department course')); ?> <?php if($enrollment->semester): ?> · <?php echo e($enrollment->semester->name); ?> <?php endif; ?></p>
                                </div>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-black text-emerald-700">Enrolled</span>
                            </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                            <p class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-500">You are not currently enrolled in any course. Use a course join link from your lecturer to enroll.</p>
                        <?php endif; ?>
                    </div>

                    <?php if($inactiveEnrollments->isNotEmpty()): ?>
                        <div class="mt-5">
                            <h3 class="text-xs font-black uppercase tracking-wide text-slate-400">Previous / dropped</h3>
                            <div class="mt-2 space-y-2">
                                <?php $__currentLoopData = $inactiveEnrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enrollment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3 text-sm">
                                        <span class="text-slate-700"><?php echo e($enrollment->course?->code); ?> — <?php echo e($enrollment->course?->name); ?></span>
                                        <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-[11px] font-bold text-slate-600"><?php echo e(\Illuminate\Support\Str::headline($enrollment->status)); ?></span>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($invitations->isNotEmpty()): ?>
                        <div class="mt-5">
                            <h3 class="text-xs font-black uppercase tracking-wide text-slate-400">Pending course invitations</h3>
                            <div class="mt-2 space-y-2">
                                <?php $__currentLoopData = $invitations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm">
                                        <div>
                                            <p class="font-bold text-amber-900"><?php echo e($invitation->course?->code); ?> — <?php echo e($invitation->course?->name); ?></p>
                                            <p class="text-xs text-amber-700">Expires <?php echo e($invitation->expires_at?->diffForHumans()); ?></p>
                                        </div>
                                        <?php if($invitation->course): ?>
                                            <a href="<?php echo e(route('courses.join.link', $invitation->course->uuid)); ?>" class="rounded-xl bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-700">Join course</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            
            <?php if($user->isLecturer() || $user->isAdmin()): ?>
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-black text-slate-950">Teaching & management</h2>
                    <div class="mt-4 space-y-2">
                        <?php $__empty_0 = true; $__currentLoopData = $teachingCourses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                            <a href="<?php echo e($assignment->course ? route('courses.show', $assignment->course) : '#'); ?>" class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 p-4 hover:border-indigo-200">
                                <div class="min-w-0">
                                    <p class="text-xs font-black uppercase tracking-wide text-indigo-600"><?php echo e($assignment->course?->code); ?></p>
                                    <p class="truncate font-bold text-slate-900"><?php echo e($assignment->course?->name); ?></p>
                                    <?php if($assignment->semester): ?><p class="text-xs text-slate-500"><?php echo e($assignment->semester->name); ?></p><?php endif; ?>
                                </div>
                                <span class="rounded-full bg-indigo-50 px-3 py-1 text-[11px] font-black text-indigo-700"><?php echo e($assignment->is_coordinator ? 'Coordinator' : 'Teaching team'); ?></span>
                            </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                            <p class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-500">No course assignments recorded for the active semester.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-black text-slate-950">Role & permissions</h2>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-600"><?php echo e($permissions->count()); ?> permissions</span>
                </div>
                <p class="mt-2 text-sm text-slate-600">Platform role: <strong><?php echo e(\Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $user->role))); ?></strong><?php if($user->account_type): ?> · Account type: <strong><?php echo e(\Illuminate\Support\Str::headline(str_replace('_', ' ', $user->account_type))); ?></strong><?php endif; ?></p>
                <div class="mt-4 flex flex-wrap gap-1.5">
                    <?php $__empty_0 = true; $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                        <span class="rounded-full bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200"><?php echo e($permission); ?></span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                        <span class="text-sm text-slate-500">No permissions are granted to this role.</span>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <aside class="space-y-6">
            
            <section class="rounded-3xl border p-6 shadow-sm <?php echo e($restrictions === [] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'); ?>">
                <h2 class="text-sm font-black text-slate-950">Access & restrictions</h2>
                <?php if($restrictions === []): ?>
                    <p class="mt-2 text-sm font-semibold text-emerald-800">Your account has no access restrictions.</p>
                <?php else: ?>
                    <ul class="mt-2 space-y-2 text-xs leading-5 text-amber-900">
                        <?php $__currentLoopData = $restrictions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $restriction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="rounded-xl bg-white/70 p-3"><?php echo e($restriction); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                <?php endif; ?>
                <p class="mt-3 text-[11px] text-slate-500">
                    Course enrollment scope: <?php echo e($university ? 'institution' : 'independent only'); ?><?php if($departmentRestricted && $university): ?> · department-restricted <?php endif; ?>.
                </p>
            </section>

            
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-black text-slate-950">Account details</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Email</dt><dd class="mt-0.5 font-semibold text-slate-800 break-all"><?php echo e($user->email); ?></dd></div>
                    <?php if($user->phone): ?><div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Phone</dt><dd class="mt-0.5 font-semibold text-slate-800"><?php echo e($user->phone); ?></dd></div><?php endif; ?>
                    <?php if($user->programme): ?><div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Programme</dt><dd class="mt-0.5 font-semibold text-slate-800"><?php echo e($user->programme); ?></dd></div><?php endif; ?>
                    <?php if($user->academic_level): ?><div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Academic level</dt><dd class="mt-0.5 font-semibold text-slate-800"><?php echo e($user->academic_level); ?></dd></div><?php endif; ?>
                    <?php if($user->location || $user->country_code): ?><div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Location</dt><dd class="mt-0.5 font-semibold text-slate-800"><?php echo e(trim(($user->location ?? '').' '.($user->country_code ?? ''))); ?></dd></div><?php endif; ?>
                    <div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Profile visibility</dt><dd class="mt-0.5 font-semibold text-slate-800"><?php echo e(\Illuminate\Support\Str::headline($user->profile_visibility ?? 'public')); ?></dd></div>
                    <div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Email verified</dt><dd class="mt-0.5 font-semibold text-slate-800"><?php echo e($user->email_verified_at ? 'Yes' : 'No'); ?></dd></div>
                    <div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Onboarding</dt><dd class="mt-0.5 font-semibold text-slate-800"><?php echo e($user->onboarding_completed_at ? 'Complete' : 'Incomplete'); ?></dd></div>
                    <div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Member since</dt><dd class="mt-0.5 font-semibold text-slate-800"><?php echo e($user->created_at?->format('M j, Y') ?? '—'); ?></dd></div>
                    <div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Last login</dt><dd class="mt-0.5 font-semibold text-slate-800"><?php echo e($user->last_login_at?->diffForHumans() ?? '—'); ?></dd></div>
                </dl>
            </section>

            
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-black text-slate-950">Manage</h2>
                <div class="mt-3 grid gap-2 text-xs font-bold text-indigo-700">
                    <?php if(Route::has('knowledge.creator.edit')): ?>
                        <a href="<?php echo e(route('knowledge.creator.edit')); ?>" class="rounded-xl bg-slate-50 px-3 py-2.5 hover:bg-indigo-50">Public creator profile</a>
                    <?php endif; ?>
                    <?php if(Route::has('security.show')): ?>
                        <a href="<?php echo e(route('security.show')); ?>" class="rounded-xl bg-slate-50 px-3 py-2.5 hover:bg-indigo-50">Security & two-factor</a>
                    <?php endif; ?>
                    <?php if(Route::has('notifications.settings')): ?>
                        <a href="<?php echo e(route('notifications.settings')); ?>" class="rounded-xl bg-slate-50 px-3 py-2.5 hover:bg-indigo-50">Notification preferences</a>
                    <?php endif; ?>
                    <?php if($user->isStudent() && Route::has('student.learning-profile')): ?>
                        <a href="<?php echo e(route('student.learning-profile')); ?>" class="rounded-xl bg-slate-50 px-3 py-2.5 hover:bg-indigo-50">AI learning preferences</a>
                    <?php endif; ?>
                </div>
            </section>
        </aside>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/profile/show.blade.php ENDPATH**/ ?>