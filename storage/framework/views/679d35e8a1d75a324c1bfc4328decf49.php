<?php $__env->startSection('title', 'Permission Management'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Permission Management</h1>
        <a href="<?php echo e(route('admin.settings')); ?>" class="text-blue-600 hover:underline text-sm">Back to Settings</a>
    </div>

    <?php if(session('success')): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-bold">Role-Based Permissions</h2>
            <p class="text-sm text-gray-600">View and manage permissions for each role</p>
        </div>
        <div class="p-6">
            <!-- Role Tabs -->
            <div class="mb-6">
                <div class="flex flex-wrap gap-2">
                    <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $roleKey => $roleData): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="#role-<?php echo e($roleKey); ?>" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                                  <?php echo e($loop->first ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'); ?>">
                            <?php echo e(ucfirst(str_replace('_', ' ', $roleKey))); ?>

                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>

            <!-- Permissions by Role -->
            <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $roleKey => $roleData): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div id="role-<?php echo e($roleKey); ?>" class="mb-8">
                    <h3 class="text-md font-bold mb-4 pb-2 border-b"><?php echo e(ucfirst(str_replace('_', ' ', $roleKey))); ?> Permissions</h3>
                    
                    <?php $__currentLoopData = $roleData['groups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupName => $permissions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3"><?php echo e($groupName); ?></h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <?php $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <div class="flex items-center p-3 border rounded hover:bg-gray-50">
                                        <input type="checkbox" 
                                               id="perm_<?php echo e($roleKey); ?>_<?php echo e($permission->value); ?>"
                                               name="permissions[<?php echo e($roleKey); ?>][]"
                                               value="<?php echo e($permission->value); ?>"
                                               <?php echo e(in_array($permission, $roleData['assigned']) ? 'checked' : ''); ?>

                                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <label for="perm_<?php echo e($roleKey); ?>_<?php echo e($permission->value); ?>" class="ml-2 block text-sm text-gray-700">
                                            <?php echo e(ucfirst(str_replace('_', ' ', $permission->value))); ?>

                                        </label>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-bold">Permission Descriptions</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php $__currentLoopData = $allPermissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="p-3 border rounded">
                        <p class="font-medium text-sm"><?php echo e(str_replace('_', ' ', $permission->value)); ?></p>
                        <p class="text-xs text-gray-500"><?php echo e($permission->value); ?></p>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    </div>
</div>

<?php $__env->startSection('scripts'); ?>
<script>
    document.querySelectorAll('a[href^="#role-"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
</script>
<?php $__env->stopSection(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/settings/permissions.blade.php ENDPATH**/ ?>