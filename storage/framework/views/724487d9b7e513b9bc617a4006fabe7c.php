<?php $__env->startSection('title', 'Student Dashboard'); ?>
<?php $__env->startSection('page-title', 'Student Dashboard'); ?>
<?php $__env->startSection('page-subtitle', 'Courses, submissions, groups, and progress'); ?>

<?php $__env->startSection('content'); ?>
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">My Courses</p>
        <h3 class="mt-2 text-3xl font-semibold text-slate-900"><?php echo e($enrollments->count()); ?></h3>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">My Groups</p>
        <h3 class="mt-2 text-3xl font-semibold text-emerald-600"><?php echo e($groups->count()); ?></h3>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">Submissions</p>
        <h3 class="mt-2 text-3xl font-semibold text-blue-600"><?php echo e($submissions->count()); ?></h3>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">Pending Payments</p>
        <h3 class="mt-2 text-3xl font-semibold text-amber-600"><?php echo e($pendingInvoices->count()); ?></h3>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold">My Courses</h3>
            <a href="<?php echo e(route('courses.index')); ?>" class="text-sm text-blue-600 hover:underline">Browse</a>
        </div>

        <?php if($enrollments->isEmpty()): ?>
            <p class="text-slate-500">You have not enrolled in any courses yet.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php $__currentLoopData = $enrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enrollment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e(route('courses.index', $enrollment->course)); ?>" class="block rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                        <p class="font-medium text-slate-900"><?php echo e($enrollment->course->name); ?></p>
                        <p class="text-sm text-slate-500"><?php echo e($enrollment->course->code); ?></p>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold">My Groups</h3>
            <a href="<?php echo e(route('groups.index')); ?>" class="text-sm text-blue-600 hover:underline">View all</a>
        </div>

        <?php if($groups->isEmpty()): ?>
            <p class="text-slate-500">You haven't joined any groups yet.</p>
            <a href="<?php echo e(route('groups.create')); ?>" class="inline-flex mt-3 text-emerald-600 hover:underline">Create a group</a>
        <?php else: ?>
            <div class="space-y-3">
                <?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e(route('groups.show', $group)); ?>" class="block rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                        <p class="font-medium text-slate-900"><?php echo e($group->name); ?></p>
                        <p class="text-sm text-slate-500"><?php echo e($group->course->name); ?></p>
                        <p class="text-xs text-slate-400 mt-1">
                            <?php echo e($group->members->count()); ?>/<?php echo e($group->max_members); ?> members
                        </p>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold">Recent Submissions</h3>
            <a href="<?php echo e(route('submissions.dashboard')); ?>" class="text-sm text-blue-600 hover:underline">Open</a>
        </div>

        <?php if($submissions->isEmpty()): ?>
            <p class="text-slate-500">You have no submissions yet.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php $__currentLoopData = $submissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e(route('submissions.show', $submission)); ?>" class="block rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                        <p class="font-medium text-slate-900"><?php echo e($submission->title); ?></p>
                        <p class="text-sm text-slate-500"><?php echo e($submission->course->name); ?></p>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <a href="<?php echo e(route('submissions.create')); ?>" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 text-white px-5 py-3 font-medium hover:bg-slate-800 transition">
        + New Submission
    </a>
    <a href="<?php echo e(route('groups.index')); ?>" class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 text-white px-5 py-3 font-medium hover:bg-emerald-700 transition">
        My Groups
    </a>
    <a href="<?php echo e(route('attendance.my')); ?>" class="inline-flex items-center justify-center rounded-2xl bg-slate-200 text-slate-900 px-5 py-3 font-medium hover:bg-slate-300 transition">
        View Attendance
    </a>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Desktop\uni-management-system\resources\views/dashboard/student.blade.php ENDPATH**/ ?>