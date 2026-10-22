
<?php ($steps = $steps ?? []); ?>

<a href="<?php echo e(route('ai.assistant')); ?>" class="inline-flex w-fit items-center gap-1.5 text-xs font-bold text-indigo-700 hover:text-indigo-900">
    <span aria-hidden="true">&larr;</span> Back to AI Assistant
</a>

<?php if($steps !== []): ?>
    <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 text-xs leading-5 text-indigo-900">
        <p class="text-xs font-black uppercase tracking-wide text-indigo-700"><?php echo e($title ?? 'How to use this tool'); ?></p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <?php $__currentLoopData = $steps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li><?php echo e($step); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ol>
        <?php if(!empty($tip)): ?>
            <p class="mt-2 rounded-xl bg-white/70 p-2.5"><strong>Tip:</strong> <?php echo e($tip); ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/student/_help.blade.php ENDPATH**/ ?>