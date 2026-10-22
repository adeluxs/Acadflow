<?php $__env->startSection('title', 'Manage Faculties'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Manage Faculties</h1>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create', App\Models\Faculty::class)): ?>
        <button type="button" onclick="document.getElementById('facultyCreateModal').classList.remove('hidden')" class="rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700">Add Faculty</button>
        <?php endif; ?>
    </div>

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3"><label class="text-sm">Faculty name or code<input type="search" name="q" maxlength="100" value="<?php echo e(request('q')); ?>" class="mt-1 block rounded-lg border-slate-300"></label><button class="rounded-lg bg-indigo-600 px-4 py-2 text-white">Search</button><a href="<?php echo e(route('admin.faculties')); ?>" class="px-2 py-2 text-sm text-indigo-600">Clear</a></form>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Departments</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dean</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php $__empty_0 = true; $__currentLoopData = $faculties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $faculty): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                    <tr>
                        <td class="px-6 py-4"><?php echo e($faculty->name); ?><span class="block text-xs text-slate-500"><?php echo e($faculty->university?->name); ?></span></td>
                        <td class="px-6 py-4"><?php echo e($faculty->code); ?></td>
                        <td class="px-6 py-4"><?php echo e($faculty->departments_count); ?></td>
                        <td class="px-6 py-4"><?php echo e($faculty->dean?->full_name ?? 'Not assigned'); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo e($faculty->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo e($faculty->is_active ? 'Active' : 'Inactive'); ?>

                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <a href="<?php echo e(route('admin.faculties.edit', $faculty)); ?>" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No faculties found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-4"><?php echo e($faculties->links()); ?></div>
</div>

<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create', App\Models\Faculty::class)): ?>
<div id="facultyCreateModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
    <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl">
        <div class="flex items-center justify-between"><h2 class="text-xl font-bold">Add Faculty</h2><button type="button" onclick="document.getElementById('facultyCreateModal').classList.add('hidden')" aria-label="Close">×</button></div>
        <form method="POST" action="<?php echo e(route('admin.faculties.create')); ?>" class="mt-5 grid gap-4 md:grid-cols-2"><?php echo csrf_field(); ?>
            <?php if(auth()->user()->isSuperAdmin()): ?><?php if (isset($component)) { $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-lookup','data' => ['class' => 'md:col-span-2','name' => 'university_id','kind' => 'universities','label' => 'University','selected' => $selectedUniversity,'required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-lookup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'md:col-span-2','name' => 'university_id','kind' => 'universities','label' => 'University','selected' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedUniversity),'required' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f)): ?>
<?php $attributes = $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f; ?>
<?php unset($__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f)): ?>
<?php $component = $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f; ?>
<?php unset($__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f); ?>
<?php endif; ?><?php endif; ?>
            <label>Faculty name<input name="name" required class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label>Short name<input name="short_name" required maxlength="80" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label>Code<input name="code" required maxlength="30" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <?php if (isset($component)) { $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-lookup','data' => ['name' => 'dean_id','kind' => 'deans','label' => 'Dean','placeholder' => 'Not assigned','selected' => $selectedDean,'depends' => auth()->user()->isSuperAdmin() ? 'university_id' : null]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-lookup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'dean_id','kind' => 'deans','label' => 'Dean','placeholder' => 'Not assigned','selected' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedDean),'depends' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(auth()->user()->isSuperAdmin() ? 'university_id' : null)]); ?>
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
            <label class="flex items-center gap-2 md:col-span-2"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <div class="flex justify-end gap-3 md:col-span-2"><button type="button" onclick="document.getElementById('facultyCreateModal').classList.add('hidden')" class="rounded-xl border px-4 py-2">Cancel</button><button class="rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white">Create faculty</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/admin/faculties.blade.php ENDPATH**/ ?>