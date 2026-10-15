<?php $__env->startSection('content'); ?>
<div class="min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <div class="bg-white shadow sm:rounded-lg p-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-6">Student Submissions to Review</h1>

            <form method="GET" action="<?php echo e(route('submissions.lecturer-index')); ?>" class="mb-6 flex flex-wrap items-end gap-3">
                <label class="text-xs font-semibold text-gray-600">Search
                    <input type="text" name="q" value="<?php echo e(request('q')); ?>" placeholder="Student name or title" class="mt-1 block w-56 rounded-md border-gray-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-gray-600">Course
                    <select name="course_id" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option value="">All courses</option>
                        <?php $__currentLoopData = $courses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $course): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($course->id); ?>" <?php if((int) request('course_id') === $course->id): echo 'selected'; endif; ?>><?php echo e($course->code); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </label>
                <label class="text-xs font-semibold text-gray-600">Assignment
                    <select name="task_id" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option value="">All assignments</option>
                        <?php $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($task->id); ?>" <?php if((int) request('task_id') === $task->id): echo 'selected'; endif; ?>><?php echo e(\Str::limit($task->title, 40)); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </label>
                <label class="text-xs font-semibold text-gray-600">Status
                    <select name="status" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option value="">Any status</option>
                        <?php $__currentLoopData = ['submitted', 'under_review', 'graded', 'approved', 'correction_requested', 'resubmitted', 'draft']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($status); ?>" <?php if(request('status') === $status): echo 'selected'; endif; ?>><?php echo e(ucfirst(str_replace('_', ' ', $status))); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </label>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">Filter</button>
                <?php if(request()->hasAny(['q', 'course_id', 'task_id', 'status'])): ?>
                    <a href="<?php echo e(route('submissions.lecturer-index')); ?>" class="text-xs font-semibold text-gray-500 hover:text-gray-700">Clear</a>
                <?php endif; ?>
            </form>

            <?php if($submissions->isEmpty()): ?>
                <p class="text-gray-500 text-center py-8">No submissions pending review.</p>
            <?php else: ?>
                <form method="POST" action="<?php echo e(route('submissions.bulk')); ?>">
                    <?php echo csrf_field(); ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Select</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Course</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Submitted</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php $__currentLoopData = $submissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <input type="checkbox" name="submission_ids[]" value="<?php echo e($submission->id); ?>" class="rounded border-gray-300">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo e($submission->user->first_name); ?> <?php echo e($submission->user->last_name); ?>

                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo e($submission->course->code); ?>

                                    </td>

                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo e(\Str::limit($submission->title, 30)); ?>

                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold
                                            <?php if($submission->status === 'submitted'): ?>
                                                bg-yellow-100 text-yellow-800
                                            <?php elseif($submission->status === 'graded'): ?>
                                                bg-green-100 text-green-800
                                            <?php elseif($submission->status === 'under_review'): ?>
                                                bg-blue-100 text-blue-800
                                            <?php else: ?>
                                                bg-gray-100 text-gray-800
                                            <?php endif; ?>">
                                            <?php echo e(ucfirst(str_replace('_', ' ', $submission->status))); ?>

                                        </span>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo e($submission->submitted_at?->format('M d, Y') ?? 'Pending'); ?>

                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col gap-2">

                                            <!-- Review -->
                                            <a href="<?php echo e(route('submissions.review', $submission)); ?>"
                                                class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-md bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700 transition-colors">

                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="w-4 h-4"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    stroke-width="2">
                                                    <path stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0s-3-7-9-7-9 7-9 7 3 7 9 7 9-7 9-7z" />
                                                </svg>

                                                <span>Review</span>
                                            </a>

                                            <!-- AI Analysis -->
                                            <a href="<?php echo e(route('ai.submission.analysis', $submission)); ?>"
                                                class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-md bg-purple-600 text-white text-xs font-medium hover:bg-purple-700 transition-colors">

                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="w-4 h-4"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    stroke-width="2">
                                                    <path stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M9.75 3v2.25M14.25 3v2.25M3 9.75h2.25M18.75 9.75H21M5.636 5.636l1.591 1.591M16.773 16.773l1.591 1.591M5.636 18.364l1.591-1.591M16.773 7.227l1.591-1.591M12 7a5 5 0 100 10 5 5 0 000-10z" />
                                                </svg>

                                                <span>AI Analysis</span>
                                            </a>

                                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('feature', 'document_generation')): ?>
                                                <!-- Grade Report -->
                                                <a href="<?php echo e(route('export.grade-report', $submission)); ?>"
                                                    target="_blank"
                                                    class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-md bg-green-600 text-white text-xs font-medium hover:bg-green-700 transition-colors">

                                                    <svg xmlns="http://www.w3.org/2000/svg"
                                                        class="w-4 h-4"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                        stroke="currentColor"
                                                        stroke-width="2">
                                                        <path stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            d="M9 12h6m-6 4h6M8 4h8l4 4v12a2 2 0 01-2 2H8a2 2 0 01-2-2V6a2 2 0 012-2z" />
                                                    </svg>

                                                    <span>Grade Report</span>
                                                </a>
                                            <?php endif; ?>

                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <select name="action" class="rounded-md border-gray-300 text-sm">
                        <option value="approve">Approve selected</option>
                        <option value="request_correction">Request correction for selected</option>
                    </select>
                    <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">Apply to selected</button>
                    <span class="text-xs text-gray-500">Each submission is checked against your permissions before it changes.</span>
                </div>
                </form>

                <div class="mt-6">
                    <?php echo e($submissions->links()); ?>

                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/submissions/lecturer-index.blade.php ENDPATH**/ ?>