<?php $__env->startSection('title', 'Send Announcement'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8 max-w-3xl">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Send System Announcement</h1>
        <p class="text-gray-600 mt-2">Broadcast a message to users across all channels</p>
    </div>

    <form action="<?php echo e(route('admin.notifications.send-announcement')); ?>" method="POST" class="bg-white rounded-lg shadow p-8">
        <?php echo csrf_field(); ?>

        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Title *</label>
            <input type="text" name="title" value="<?php echo e(old('title')); ?>"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                   required>
            <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="text-red-600 text-sm"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Message *</label>
            <textarea name="message" rows="6"
                      class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                      required><?php echo e(old('message')); ?></textarea>
            <?php $__errorArgs = ['message'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="text-red-600 text-sm"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Target Audience *</label>
            <select name="target" id="target" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" required onchange="toggleDept()">
                <option value="">Select target</option>
                <option value="all">All Users</option>
                <option value="students">All Students</option>
                <option value="lecturers">All Lecturers</option>
                <option value="department">Specific Department</option>
            </select>
            <?php $__errorArgs = ['target'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="text-red-600 text-sm"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div class="mb-6" id="department-select" style="display:none;">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Department</label>
            <select name="department_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">Select department</option>
                <?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dept): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($dept->id); ?>"><?php echo e($dept->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <?php $__errorArgs = ['department_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="text-red-600 text-sm"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div class="flex gap-3 pt-6 border-t">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                Send Announcement
            </button>
            <a href="<?php echo e(route('admin.notifications.index')); ?>" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
function toggleDept() {
    const target = document.getElementById('target').value;
    const deptSelect = document.getElementById('department-select');
    deptSelect.style.display = target === 'department' ? 'block' : 'none';
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/admin/notifications/announce.blade.php ENDPATH**/ ?>