<?php $__env->startSection('title', 'Department Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Department Dashboard</h1>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-gray-500 text-sm">Total Students</div>
            <div class="text-3xl font-bold"><?php echo e($stats['total_students']); ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-gray-500 text-sm">Total Lecturers</div>
            <div class="text-3xl font-bold"><?php echo e($stats['total_lecturers']); ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-gray-500 text-sm">Total Courses</div>
            <div class="text-3xl font-bold"><?php echo e($stats['total_courses']); ?></div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Quick Links</h2>
            <div class="space-y-2">
                <a href="<?php echo e(route('admin.courses')); ?>" class="block p-3 bg-gray-50 rounded hover:bg-gray-100">Manage Courses</a>
                <a href="" class="block p-3 bg-gray-50 rounded hover:bg-gray-100">Manage Users</a>
                <a href="<?php echo e(route('admin.billing')); ?>" class="block p-3 bg-gray-50 rounded hover:bg-gray-100">Billing</a>
                <a href="<?php echo e(route('admin.reports')); ?>" class="block p-3 bg-gray-50 rounded hover:bg-gray-100">Reports</a>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/admin/department.blade.php ENDPATH**/ ?>