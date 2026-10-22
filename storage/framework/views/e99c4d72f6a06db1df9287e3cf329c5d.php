<?php $__env->startSection('title', 'Manage Users'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Manage Users</h1>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create', App\Models\User::class)): ?>
        <button onclick="document.getElementById('inviteModal').classList.remove('hidden')" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            Invite User
        </button>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="grid items-end gap-4 md:grid-cols-4">
            <label class="text-sm">Name or email<input type="search" name="q" maxlength="100" value="<?php echo e(request('q')); ?>" class="mt-1 w-full border rounded px-3 py-2" placeholder="Search users"></label>
            <label class="text-sm">Role<select name="role" class="mt-1 w-full border rounded px-3 py-2">
                <option value="">All Roles</option>
                <?php $__currentLoopData = ['member'=>'Member','student'=>'Student','lecturer'=>'Lecturer','department_admin'=>'Department Admin','university_admin'=>'University Admin']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($value); ?>" <?php if(request('role')===$value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select></label>
            <?php if (isset($component)) { $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-lookup','data' => ['name' => 'department_id','kind' => 'departments','label' => 'Department','placeholder' => 'All departments','selected' => $selectedDepartment]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-lookup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'department_id','kind' => 'departments','label' => 'Department','placeholder' => 'All departments','selected' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedDepartment)]); ?>
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
            <div class="space-y-2"><label class="block text-sm">Status<select name="status" class="mt-1 w-full border rounded px-3 py-2"><option value="">All statuses</option><option value="active" <?php if(request('status')==='active'): echo 'selected'; endif; ?>>Active</option><option value="inactive" <?php if(request('status')==='inactive'): echo 'selected'; endif; ?>>Inactive</option></select></label><button type="submit" class="bg-gray-200 px-4 py-2 rounded hover:bg-gray-300">Filter</button> <a href="<?php echo e(route('admin.users')); ?>" class="text-sm text-indigo-600">Clear</a></div>
        </div>
    </form>

    <?php if(session('success')): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php $__empty_0 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                    <tr>
                        <td class="px-6 py-4"><?php echo e($user->first_name); ?> <?php echo e($user->last_name); ?></td>
                        <td class="px-6 py-4"><?php echo e($user->email); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?php echo e(ucfirst($user->role)); ?>

                            </span>
                        </td>
                        <td class="px-6 py-4"><?php echo e($user->department?->name); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo e($user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo e($user->is_active ? 'Active' : 'Inactive'); ?>

                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <a href="<?php echo e(route('admin.users.edit', $user)); ?>" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        <?php echo e($users->links()); ?>

    </div>
</div>

<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create', App\Models\User::class)): ?>
<!-- Invite Modal -->
<div id="inviteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Invite User</h2>
        <form method="POST" action="<?php echo e(route('admin.users.invite')); ?>">
            <?php echo csrf_field(); ?>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                <input name="email" type="email" class="w-full px-3 py-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Role</label>
                <select name="role" class="w-full px-3 py-2 border rounded" required>
                    <option value="student">Student</option>
                    <option value="lecturer">Lecturer</option>
                    <option value="department_admin">Department Admin</option>
                </select>
            </div>
            <?php if (isset($component)) { $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-lookup','data' => ['class' => 'mb-4','name' => 'department_id','kind' => 'departments','label' => 'Department','placeholder' => 'Select department','selected' => $inviteDepartment,'required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-lookup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mb-4','name' => 'department_id','kind' => 'departments','label' => 'Department','placeholder' => 'Select department','selected' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($inviteDepartment),'required' => true]); ?>
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
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('inviteModal').classList.add('hidden')" class="px-4 py-2 border rounded">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Send Invite</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/admin/users.blade.php ENDPATH**/ ?>