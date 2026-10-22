<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'kind', 'label', 'selected' => null, 'placeholder' => 'Select an option', 'required' => false, 'depends' => null, 'filters' => [], 'options' => []]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['name', 'kind', 'label', 'selected' => null, 'placeholder' => 'Select an option', 'required' => false, 'depends' => null, 'filters' => [], 'options' => []]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php ($lookupId = 'lookup-'.\Illuminate\Support\Str::uuid()); ?>
<div <?php echo e($attributes->class(['min-w-0 space-y-1'])); ?> data-admin-lookup data-url="<?php echo e(route('admin.lookups', ['kind' => $kind] + $filters)); ?>" <?php if($depends): ?> data-depends="<?php echo e($depends); ?>" <?php endif; ?>>
    <label for="<?php echo e($lookupId); ?>" class="block text-sm font-medium"><?php echo e($label); ?></label>
    <input type="search" data-lookup-search maxlength="100" autocomplete="off" aria-label="Search <?php echo e(strtolower($label)); ?>" aria-controls="<?php echo e($lookupId); ?>" placeholder="Search <?php echo e(strtolower($label)); ?>…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <select id="<?php echo e($lookupId); ?>" name="<?php echo e($name); ?>" data-lookup-select <?php if($required): echo 'required'; endif; ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value=""><?php echo e($placeholder); ?></option>
        <?php if($selected): ?><option value="<?php echo e($selected['id']); ?>" selected><?php echo e($selected['label']); ?></option><?php endif; ?>
        <?php $__currentLoopData = $options; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if((string)($selected['id'] ?? '') !== (string)$option['id']): ?><option value="<?php echo e($option['id']); ?>"><?php echo e($option['label']); ?></option><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <div class="flex items-center gap-2 text-xs text-slate-500"><span data-lookup-status role="status" aria-live="polite"><?php echo e($depends ? 'Choose the field above, then search.' : 'Focus or search to load options.'); ?></span><button type="button" data-lookup-more hidden class="text-indigo-600 underline">More results</button></div>
    <noscript><p class="text-xs text-slate-600">Enable JavaScript to search available options.</p></noscript>
</div>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/components/admin-lookup.blade.php ENDPATH**/ ?>