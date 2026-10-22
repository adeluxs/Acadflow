<?php $__env->startSection('title', 'Audit Logs'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Audit Logs</h1>
        <a href="<?php echo e(route('admin.settings')); ?>" class="text-blue-600 hover:underline text-sm">Back to Settings</a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow mb-6 p-4">
        <form method="GET" action="<?php echo e(route('admin.settings.audit-logs')); ?>" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                <input name="action" value="<?php echo e(request('action')); ?>" maxlength="100" list="audit-actions" placeholder="All actions" class="w-full px-3 py-2 border rounded">
                <datalist id="audit-actions"><?php $__currentLoopData = ['login','logout','setting_updated','create','update','delete']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($action); ?>"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></datalist>
            </div>
            <div>
                <?php if (isset($component)) { $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-lookup','data' => ['name' => 'user_id','kind' => 'audit_users','label' => 'User','placeholder' => 'All users','selected' => $selectedUser]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-lookup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'user_id','kind' => 'audit_users','label' => 'User','placeholder' => 'All users','selected' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedUser)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f)): ?>
<?php $attributes = $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f; ?>
<?php unset($__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f)): ?>
<?php $component = $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f; ?>
<?php unset($__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f); ?>
<?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?php echo e(request('date_from')); ?>" class="w-full px-3 py-2 border rounded">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" name="date_to" value="<?php echo e(request('date_to')); ?>" class="w-full px-3 py-2 border rounded">
            </div>
            <div class="md:col-span-4 flex gap-2">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm">
                    Filter
                </button>
                <a href="<?php echo e(route('admin.settings.audit-logs')); ?>" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 text-sm">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php $__empty_0 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo e($log->created_at->format('Y-m-d H:i:s')); ?>

                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo e($log->user ? ($log->user->full_name ?: $log->user->email) : 'System'); ?>

                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?php switch($log->action):
                                        case ('setting_updated'): ?>
                                            bg-blue-100 text-blue-800
                                            <?php break; ?>
                                        <?php case ('login'): ?>
                                            bg-green-100 text-green-800
                                            <?php break; ?>
                                        <?php case ('delete'): ?>
                                            bg-red-100 text-red-800
                                            <?php break; ?>
                                        <?php default: ?>
                                            bg-gray-100 text-gray-800
                                    <?php endswitch; ?>
                                ">
                                    <?php echo e(ucfirst(str_replace('_', ' ', $log->action))); ?>

                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <?php if($log->field_name): ?>
                                    <strong><?php echo e($log->field_name); ?></strong><br>
                                    <?php if($log->old_value): ?>
                                        <span class="text-red-600">Old: <?php echo e(Str::limit($log->old_value, 50)); ?></span><br>
                                    <?php endif; ?>
                                    <?php if($log->new_value): ?>
                                        <span class="text-green-600">New: <?php echo e(Str::limit($log->new_value, 50)); ?></span>
                                    <?php endif; ?>
                                <?php elseif($log->model_type): ?>
                                    <?php echo e(class_basename($log->model_type)); ?> #<?php echo e($log->model_id); ?>

                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                <?php echo e($log->ip_address); ?>

                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                No audit logs found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t">
            <?php echo e($logs->links()); ?>

        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/settings/audit-logs.blade.php ENDPATH**/ ?>