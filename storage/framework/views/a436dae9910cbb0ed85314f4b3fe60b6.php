<?php $__env->startSection('title', 'Edit Institution'); ?>
<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-4xl">
    <a href="<?php echo e(route('admin.universities')); ?>" class="acad-link text-sm">← Back to institutions</a>
    <div class="mt-2"><p class="text-xs font-semibold uppercase tracking-[.18em] text-indigo-600">Institution settings</p><h1 class="mt-1 text-2xl font-bold"><?php echo e($university->name); ?></h1></div>
    <form method="POST" action="<?php echo e(route('admin.universities.update',$university)); ?>" class="acad-card mt-6 grid gap-5 p-6 md:grid-cols-2"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
        <label class="md:col-span-2 text-sm font-medium">Institution name<input name="name" required value="<?php echo e(old('name',$university->name)); ?>" class="mt-1 w-full rounded-xl"></label>
        <label class="text-sm font-medium">Short name<input name="short_name" required value="<?php echo e(old('short_name',$university->short_name)); ?>" class="mt-1 w-full rounded-xl"></label>
        <label class="text-sm font-medium">Code<input name="code" maxlength="10" required value="<?php echo e(old('code',$university->code)); ?>" class="mt-1 w-full rounded-xl uppercase"></label>
        <label class="text-sm font-medium">Institution type<select name="institution_type" required class="mt-1 w-full rounded-xl"><option value="university" <?php if(old('institution_type',$university->institution_type)==='university'): echo 'selected'; endif; ?>>University</option><option value="polytechnic" <?php if(old('institution_type',$university->institution_type)==='polytechnic'): echo 'selected'; endif; ?>>Polytechnic</option></select></label>
        <label class="text-sm font-medium">Ownership<select name="ownership" class="mt-1 w-full rounded-xl"><option value="">Select</option><?php $__currentLoopData = ['Federal','State','Private']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($option); ?>" <?php if(old('ownership',$university->ownership)===$option): echo 'selected'; endif; ?>><?php echo e($option); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
        <label class="text-sm font-medium">State<input name="state" value="<?php echo e(old('state',$university->state)); ?>" class="mt-1 w-full rounded-xl"></label>
        <label class="text-sm font-medium">Regulator<input name="regulator" value="<?php echo e(old('regulator',$university->regulator)); ?>" class="mt-1 w-full rounded-xl"></label>
        <label class="text-sm font-medium">Email<input type="email" name="email" value="<?php echo e(old('email',$university->email)); ?>" class="mt-1 w-full rounded-xl"></label>
        <label class="text-sm font-medium">Phone<input name="phone" value="<?php echo e(old('phone',$university->phone)); ?>" class="mt-1 w-full rounded-xl"></label>
        <label class="text-sm font-medium">Website<input type="url" name="website" value="<?php echo e(old('website',$university->website)); ?>" class="mt-1 w-full rounded-xl"></label>
        <label class="text-sm font-medium">Timezone<input name="timezone" required value="<?php echo e(old('timezone',$university->timezone ?: config('app.timezone'))); ?>" class="mt-1 w-full rounded-xl"></label>
        <label class="md:col-span-2 text-sm font-medium">Address<textarea name="address" rows="3" class="mt-1 w-full rounded-xl"><?php echo e(old('address',$university->address)); ?></textarea></label>
        <?php if($university->catalog_source): ?><div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600"><p><strong>Catalogue source:</strong> <?php echo e($university->catalog_source); ?></p><p class="mt-1"><strong>Last regulator verification:</strong> <?php echo e($university->catalog_verified_at?->format('M j, Y H:i') ?? 'Fallback/manual data — verify before relying on it.'); ?></p></div><?php endif; ?>
        <label class="flex items-center gap-2 md:col-span-2 text-sm font-medium"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" <?php if(old('is_active',$university->is_active)): echo 'checked'; endif; ?>> Active institution</label>
        <div class="flex justify-end gap-3 md:col-span-2"><a href="<?php echo e(route('admin.universities')); ?>" class="rounded-xl border px-5 py-2.5">Cancel</a><button class="acad-primary-button">Save institution</button></div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/admin/universities-edit.blade.php ENDPATH**/ ?>