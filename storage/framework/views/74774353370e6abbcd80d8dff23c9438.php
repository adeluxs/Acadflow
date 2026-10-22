<?php $__env->startSection('title', 'Edit Faculty'); ?>
<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-3xl px-4 py-8"><a href="<?php echo e(route('admin.faculties')); ?>" class="text-sm text-indigo-600">← Back to faculties</a><h1 class="mt-2 text-2xl font-bold">Edit <?php echo e($faculty->name); ?></h1>
<form method="POST" action="<?php echo e(route('admin.faculties.update',$faculty)); ?>" class="mt-6 grid gap-5 rounded-2xl border bg-white p-6 md:grid-cols-2"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
<label class="md:col-span-2">Faculty name<input name="name" required value="<?php echo e(old('name',$faculty->name)); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label><label>Short name<input name="short_name" required value="<?php echo e(old('short_name',$faculty->short_name)); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label><label>Code<input name="code" required value="<?php echo e(old('code',$faculty->code)); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label><?php if (isset($component)) { $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-lookup','data' => ['class' => 'md:col-span-2','name' => 'dean_id','kind' => 'deans','label' => 'Dean','placeholder' => 'Not assigned','selected' => $selectedDean,'filters' => ['university_id' => $faculty->university_id]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-lookup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'md:col-span-2','name' => 'dean_id','kind' => 'deans','label' => 'Dean','placeholder' => 'Not assigned','selected' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedDean),'filters' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['university_id' => $faculty->university_id])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f)): ?>
<?php $attributes = $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f; ?>
<?php unset($__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f)): ?>
<?php $component = $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f; ?>
<?php unset($__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f); ?>
<?php endif; ?><label class="flex items-center gap-2 md:col-span-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" <?php if(old('is_active',$faculty->is_active)): echo 'checked'; endif; ?>> Active</label><div class="flex justify-end gap-3 md:col-span-2"><a href="<?php echo e(route('admin.faculties')); ?>" class="rounded-xl border px-5 py-2">Cancel</a><button class="rounded-xl bg-indigo-600 px-5 py-2 font-semibold text-white">Save faculty</button></div>
</form></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/admin/faculties-edit.blade.php ENDPATH**/ ?>