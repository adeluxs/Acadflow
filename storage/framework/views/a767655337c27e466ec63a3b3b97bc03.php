<?php $__env->startSection('title', 'Edit User'); ?>
<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-3xl px-4 py-8">
    <div class="mb-6"><a href="<?php echo e(route('admin.users')); ?>" class="text-sm text-indigo-600">← Back to users</a><h1 class="mt-2 text-2xl font-bold">Manage <?php echo e($user->full_name); ?></h1><p class="text-sm text-slate-600">Update identity, institutional assignment, role and account status.</p></div>
    <form method="POST" action="<?php echo e(route('admin.users.update', $user)); ?>" class="space-y-5 rounded-2xl border bg-white p-6"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
        <div class="grid gap-4 md:grid-cols-2"><label>First name<input name="first_name" required value="<?php echo e(old('first_name', $user->first_name)); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label><label>Last name<input name="last_name" required value="<?php echo e(old('last_name', $user->last_name)); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label></div>
        <div class="grid gap-4 md:grid-cols-2"><label>Role<select name="role" required class="mt-1 w-full rounded-xl border-slate-300"><?php $__currentLoopData = ['member'=>'Platform member','student'=>'Student','lecturer'=>'Lecturer','department_admin'=>'Department administrator','university_admin'=>'University administrator']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if(old('role',$user->role)===$value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label><?php if (isset($component)) { $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-lookup','data' => ['name' => 'department_id','kind' => 'departments','label' => 'Department','placeholder' => 'No department','selected' => $selectedDepartment]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-lookup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'department_id','kind' => 'departments','label' => 'Department','placeholder' => 'No department','selected' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedDepartment)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f)): ?>
<?php $attributes = $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f; ?>
<?php unset($__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f)): ?>
<?php $component = $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f; ?>
<?php unset($__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f); ?>
<?php endif; ?></div>
        <label class="flex items-center gap-3"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" <?php if(old('is_active',$user->is_active)): echo 'checked'; endif; ?>> Account is active</label>
        <div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Changing an institutional role requires a valid department. Platform members remain independent and have no forced university affiliation.</div>
        <div class="flex justify-end gap-3"><a href="<?php echo e(route('admin.users')); ?>" class="rounded-xl border px-5 py-2">Cancel</a><button class="rounded-xl bg-indigo-600 px-5 py-2 font-semibold text-white">Save account</button></div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/admin/users-edit.blade.php ENDPATH**/ ?>