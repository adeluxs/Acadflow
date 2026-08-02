<?php $__env->startSection('title', 'Manage Universities'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Manage Universities</h1>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create', App\Models\University::class)): ?>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            Add University
        </button>
        <?php endif; ?>
    </div>

    <?php if(session('success')): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php $__empty_1 = true; $__currentLoopData = $universities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $university): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td class="px-6 py-4"><?php echo e($university->name); ?></td>
                        <td class="px-6 py-4"><?php echo e($university->code); ?></td>
                        <td class="px-6 py-4"><?php echo e($university->email); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo e($university->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo e($university->is_active ? 'Active' : 'Inactive'); ?>

                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <a href="#" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No universities found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        <?php echo e($universities->links()); ?>

    </div>
</div>

<!-- Create Modal -->
<div id="createModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Add University</h2>
        <form method="POST" action="<?php echo e(route('admin.universities.create')); ?>">
            <?php echo csrf_field(); ?>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">University Name</label>
                <input name="name" type="text" class="w-full px-3 py-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Short Name</label>
                <input name="short_name" type="text" class="w-full px-3 py-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Code</label>
                <input name="code" type="text" class="w-full px-3 py-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                <input name="email" type="email" class="w-full px-3 py-2 border rounded">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Phone</label>
                <input name="phone" type="text" class="w-full px-3 py-2 border rounded">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 border rounded">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Create</button>
            </div>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Desktop\uni-management-system\resources\views/admin/universities.blade.php ENDPATH**/ ?>