<section class="rounded-2xl border bg-white p-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold">Discussion</h2>
            <p class="mt-1 text-sm text-slate-500">Ask questions, share evidence, and mention collaborators.</p>
        </div>
    </div>
    <?php if(auth()->guard()->check()): ?>
        <form method="POST" action="<?php echo e($discussionAction); ?>" class="mt-4 flex flex-col gap-2 sm:flex-row">
            <?php echo csrf_field(); ?>
            <input required name="body" maxlength="10000" class="min-w-0 flex-1 rounded-xl border-slate-300" placeholder="Add a constructive message or @mention">
            <button class="rounded-xl bg-blue-600 px-4 py-2 font-semibold text-white">Post</button>
        </form>
    <?php else: ?>
        <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Sign in to participate in this discussion.</p>
    <?php endif; ?>
    <div class="mt-5 space-y-4">
        <?php $__empty_0 = true; $__currentLoopData = $comments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
            <article class="rounded-xl border p-4">
                <div class="flex justify-between gap-3 text-xs text-slate-500">
                    <strong class="text-slate-800"><?php echo e($comment->user?->full_name ?? 'Former user'); ?></strong>
                    <span><?php echo e($comment->created_at?->diffForHumans()); ?></span>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700"><?php echo e($comment->body); ?></p>
                <?php $__currentLoopData = $comment->replies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reply): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="ml-4 mt-3 border-l-2 border-slate-200 pl-3 text-sm">
                        <strong><?php echo e($reply->user?->full_name ?? 'Former user'); ?></strong>
                        <p class="mt-1 whitespace-pre-line text-slate-700"><?php echo e($reply->body); ?></p>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php if(auth()->guard()->check()): ?>
                    <form method="POST" action="<?php echo e($discussionAction); ?>" class="mt-3 flex gap-2">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="parent_id" value="<?php echo e($comment->id); ?>">
                        <input required name="body" maxlength="10000" class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm" placeholder="Reply">
                        <button class="text-sm font-semibold text-blue-700">Reply</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
            <div class="rounded-xl border border-dashed p-6 text-center text-sm text-slate-500">No discussion messages yet. Start the conversation with a useful question or resource.</div>
        <?php endif; ?>
    </div>
    <?php if(method_exists($comments,'links')): ?><div class="mt-4"><?php echo e($comments->links()); ?></div><?php endif; ?>
</section>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/knowledge/_discussion.blade.php ENDPATH**/ ?>