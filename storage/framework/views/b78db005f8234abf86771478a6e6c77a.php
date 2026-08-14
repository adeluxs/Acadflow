<?php $__env->startSection('title', 'Notifications'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Notifications</h1>
        <div class="flex gap-2">
            <?php if($unreadCount > 0): ?>
                <form method="POST" action="<?php echo e(route('notifications.read-all')); ?>">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PUT'); ?>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Mark All Read
                    </button>
                </form>
            <?php endif; ?>
            <form method="POST" action="<?php echo e(route('notifications.clear')); ?>" onsubmit="return confirm('Clear all notifications?')">
                <?php echo csrf_field(); ?>
                <?php echo method_field('DELETE'); ?>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    Clear All
                </button>
            </form>
        </div>
    </div>

    <!-- Sidebar filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="md:col-span-1">
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="font-bold mb-3">Filter By Type</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="<?php echo e(route('notifications.index')); ?>" 
                           class="flex justify-between <?php echo e(!request()->has('filter') && !request()->has('type') ? 'font-bold text-indigo-600' : 'text-gray-600'); ?>">
                            All
                             <span class="text-gray-400"><?php echo e($typeCounts->count() ?? $notifications->total()); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo e(route('notifications.index', ['filter' => 'unread'])); ?>" 
                           class="flex justify-between <?php echo e(request('filter') === 'unread' ? 'font-bold text-indigo-600' : 'text-gray-600'); ?>">
                            Unread
                            <span class="text-gray-400"><?php echo e($unreadCount); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo e(route('notifications.index', ['filter' => 'read'])); ?>" 
                           class="flex justify-between <?php echo e(request('filter') === 'read' ? 'font-bold text-indigo-600' : 'text-gray-600'); ?>">
                            Read
                            <span class="text-gray-400"><?php echo e(($typeCounts->sum(fn($t) => $t->count) ?? $notifications->total()) - $unreadCount); ?></span>
                        </a>
                    </li>
                </ul>

                <?php if($typeCounts->count() > 0): ?>
                    <hr class="my-4">
                    <h3 class="font-bold mb-3">By Category</h3>
                    <ul class="space-y-2 text-sm">
                        <?php $__currentLoopData = $typeCounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li>
                                <a href="<?php echo e(route('notifications.index', ['type' => $type])); ?>" 
                                   class="flex justify-between text-gray-600 hover:text-indigo-600">
                                    <?php echo e(ucwords(str_replace('_', ' ', $type))); ?>

                                    <span><?php echo e($count->count); ?></span>
                                </a>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main list -->
        <div class="md:col-span-3">
            <?php if($notifications->count() > 0): ?>
                <div class="space-y-3">
                    <?php $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="bg-white rounded-lg shadow p-4 <?php echo e(is_null($notification->read_at) ? 'border-l-4 border-blue-500' : ''); ?>">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="font-semibold <?php echo e(is_null($notification->read_at) ? 'text-gray-900' : 'text-gray-700'); ?>">
                                        <?php echo e($notification->title); ?>

                                    </h4>
                                    <p class="text-gray-600 text-sm mt-1"><?php echo e($notification->message); ?></p>
                                    <div class="flex items-center gap-2 mt-2 text-xs text-gray-500">
                                        <span><?php echo e($notification->created_at?->format('M d, Y H:i') ?? '-'); ?></span>
                                        <?php if($notification->user): ?>
                                            <span>• <?php echo e($notification->user->full_name); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <?php if(is_null($notification->read_at)): ?>
                                        <form method="POST" action="<?php echo e(route('notifications.read', $notification)); ?>">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('PUT'); ?>
                                            <button type="submit" class="text-xs text-blue-600 hover:underline">Mark Read</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" action="<?php echo e(route('notifications.destroy', $notification)); ?>">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="text-xs text-red-600 hover:underline" 
                                                onclick="return confirm('Delete this notification?')">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <div class="mt-6">
                    <?php echo e($notifications->links()); ?>

                </div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <p class="text-gray-500">No notifications found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Desktop\Acadflow\resources\views/notifications/index.blade.php ENDPATH**/ ?>