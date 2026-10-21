<?php $__env->startSection('title', 'Attendance'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Attendance</h1>
        @role('lecturer')
            <a href="<?php echo e(route('attendance.lecturer')); ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Start Session
            </a>
        @endrole
    </div>

    <div class="grid gap-6 md:grid-cols-2 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">My Attendance</h2>
            <div class="text-4xl font-bold text-blue-600"><?php echo e($attendanceRate ?? 0); ?>%</div>
            <p class="text-gray-600 text-sm">Overall attendance rate</p>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Recordings</h2>
            <div class="space-y-2">
                <p>Present: <span class="font-semibold text-green-600"><?php echo e($present ?? 0); ?></span></p>
                <p>Late: <span class="font-semibold text-yellow-600"><?php echo e($late ?? 0); ?></span></p>
                <p>Absent: <span class="font-semibold text-red-600"><?php echo e($absent ?? 0); ?></span></p>
            </div>
        </div>
    </div>

    <h2 class="text-xl font-semibold mb-4">Recent Sessions</h2>
    
    <?php if($sessions->isEmpty()): ?>
        <div class="bg-gray-100 rounded-lg p-8 text-center">
            <p class="text-gray-600">No attendance sessions yet.</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php $__currentLoopData = $sessions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $session): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo e($session->course->code); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo e($session->created_at->format('M d, Y')); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php echo e($session->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'); ?>">
                                    <?php echo e($session->status === 'active' ? 'Active' : 'Closed'); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php echo e($session->records->whereIn('status', ['present', 'late'])->count()); ?> / <?php echo e($session->records->count()); ?>

                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/attendance/index.blade.php ENDPATH**/ ?>