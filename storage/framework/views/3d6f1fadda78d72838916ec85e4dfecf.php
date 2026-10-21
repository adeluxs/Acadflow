<?php $__env->startSection('title', $task->title); ?>

<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="rounded-[2rem] bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 p-7 text-white shadow-xl sm:p-9 flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-3xl font-black text-white sm:text-4xl"><?php echo e($task->title); ?></h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300"><?php echo e($task->description); ?></p>
            <div class="flex gap-4 mt-4 text-sm">
                <span class="px-3 py-1 rounded-full 
                    <?php echo e($task->status === 'published' ? 'bg-green-100 text-green-800' : ''); ?>

                    <?php echo e($task->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : ''); ?>

                    <?php echo e($task->status === 'closed' ? 'bg-red-100 text-red-800' : ''); ?>

                ">
                    <?php echo e(ucfirst($task->status)); ?>

                </span>
                <span class="text-gray-700">Type: <strong><?php echo e(ucfirst($task->type)); ?></strong></span>
            </div>
        </div>
        
        <div class="text-right space-y-2">
            <a href="<?php echo e(route('submission-tasks.extensions', [$course, $task])); ?>"
               class="block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Manage Extensions
            </a>
            <?php if($task->status === 'draft'): ?>
                <a href="<?php echo e(route('submission-tasks.edit', [$course, $task])); ?>" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm mb-2 block">
                    Edit
                </a>
                <form action="<?php echo e(route('submission-tasks.publish', [$course, $task])); ?>" method="POST" class="inline">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm"
                            onclick="return confirm('Publish this assignment?')">
                        Publish
                    </button>
                </form>
            <?php elseif($task->status === 'published'): ?>
                <form action="<?php echo e(route('submission-tasks.close', [$course, $task])); ?>" method="POST" class="inline">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition text-sm"
                            onclick="return confirm('Close this assignment?')">
                        Close
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php echo $__env->make('ai._contextual-assistant', [
        'assistantFeature' => 'assignment_assistant',
        'assistantEndpoint' => route('ai.context.assignment', [$course, $task]),
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_340px]">
        <!-- Left: Details -->
        <div class="space-y-6">
            <!-- Instructions -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Instructions</h2>
                <div class="prose prose-sm max-w-none">
                    <?php echo nl2br(e($task->instructions)); ?>

                </div>
            </div>

            <!-- File Requirements -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold text-gray-900 mb-4">File Requirements</h2>
                <ul class="space-y-3">
                    <li class="flex justify-between">
                        <span class="text-gray-700">Allowed Formats:</span>
                         <span class="font-semibold"><?php echo e(implode(', ', $task->allowed_file_types ?? [])); ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-700">Max File Size:</span>
                        <span class="font-semibold"><?php echo e($task->max_file_size_mb); ?> MB</span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-700">File Count:</span>
                        <span class="font-semibold"><?php echo e($task->min_file_count); ?> - <?php echo e($task->max_file_count); ?> files</span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-700">Max Resubmissions:</span>
                        <span class="font-semibold"><?php echo e($task->max_resubmissions ?? 'Unlimited'); ?></span>
                    </li>
                </ul>
            </div>

            <!-- Attachments -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-900">Supporting Materials</h2>
                    <?php if($task->status === 'draft' || $task->status === 'published'): ?>
                        <button onclick="document.getElementById('attachmentForm').style.display = 'block'"
                                class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                            + Add File
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Upload Form (Hidden) -->
                <div id="attachmentForm" class="bg-gray-50 p-4 rounded mb-4" style="display: none;">
                     <form action="<?php echo e(route('submission-tasks.attachment.upload', [$course, $task])); ?>" method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">File Type</label>
                            <select name="type" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                                <option value="">Select type</option>
                                <option value="template">Template</option>
                                <option value="guide">Guide</option>
                                <option value="rubric">Rubric</option>
                                <option value="example">Example</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">File</label>
                            <input type="file" name="file" class="w-full" required>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Upload
                            </button>
                            <button type="button" onclick="document.getElementById('attachmentForm').style.display = 'none'"
                                    class="px-3 py-1 bg-gray-300 text-gray-700 text-sm rounded hover:bg-gray-400">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Attachments List -->
                <?php if($task->attachments && $task->attachments->count() > 0): ?>
                    <div class="space-y-2">
                        <?php $__currentLoopData = $task->attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attachment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded border">
                            <div>
                                <p class="font-semibold text-gray-900"><?php echo e($attachment->file_name); ?></p>
                                <p class="text-xs text-gray-600"><?php echo e(ucfirst($attachment->type)); ?></p>
                            </div>
                            <div class="flex gap-2">
                                 <a href="<?php echo e(route('submission-tasks.attachment.download', [$course, $task, $attachment])); ?>"
                                    class="text-blue-600 hover:underline text-sm">Download</a>
                                <?php if($task->status === 'draft' || $task->status === 'published'): ?>
                                    <form action="<?php echo e(route('submission-tasks.attachment.delete', [$course, $task, $attachment])); ?>" 
                                          method="POST" class="inline">
                                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="text-red-600 hover:underline text-sm"
                                                onclick="return confirm('Delete this file?')">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-sm">No supporting materials uploaded yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Sidebar -->
        <div>
            <!-- Key Dates -->
            <div class="rounded-3xl border border-blue-200 bg-blue-50 p-6">
                <h3 class="font-bold text-gray-900 mb-4">Important Dates</h3>
                <ul class="space-y-3 text-sm">
                    <li>
                        <p class="text-gray-600">Opens</p>
                        <p class="font-semibold"><?php echo e($task->open_at?->format('M d, Y H:i') ?? 'Not set'); ?></p>
                    </li>
                    <li>
                        <p class="text-gray-600">Due (Soft)</p>
                        <p class="font-semibold"><?php echo e($task->due_date?->format('M d, Y H:i') ?? 'Not set'); ?></p>
                    </li>
                    <li>
                        <p class="text-gray-600">Hard Deadline</p>
                        <p class="font-semibold"><?php echo e($task->late_deadline?->format('M d, Y H:i') ?? 'Not set'); ?></p>
                    </li>
                </ul>
            </div>

            <!-- Submission Stats -->
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6">
                <h3 class="font-bold text-gray-900 mb-4">Submissions</h3>
                <div class="text-3xl font-bold text-green-600"><?php echo e($stats['total_submissions'] ?? 0); ?></div>
                <p class="text-sm text-gray-600 mt-1">of <?php echo e(($stats['total_submissions'] ?? 0) + ($nonSubmitters->total() ?? 0)); ?> enrolled students</p>
                <div class="mt-4 w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-600 h-2 rounded-full" 
                         style="width: <?php echo e((($stats['total_submissions'] ?? 0) + ($nonSubmitters->total() ?? 0)) > 0 ? (($stats['total_submissions'] ?? 0) / (($stats['total_submissions'] ?? 0) + ($nonSubmitters->total() ?? 0)) * 100) : 0); ?>%">
                    </div>
                </div>
            </div>

            <!-- Late Penalty Info -->
            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
                <h3 class="font-bold text-gray-900 mb-3">Late Submission Policy</h3>
                <ul class="space-y-2 text-sm">
                    <li class="flex items-start gap-2">
                        <span class="text-yellow-600 font-bold">•</span>
                        <span><?php echo e($task->allow_late_submissions ? 'Late submissions allowed' : 'No late submissions'); ?></span>
                    </li>
                    <?php if($task->allow_late_submissions): ?>
                        <li class="flex items-start gap-2">
                            <span class="text-yellow-600 font-bold">•</span>
                            <span><?php echo e($task->late_submission_penalty_percent); ?>% penalty applied</span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Submissions List -->
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Student Submissions</h2>
        
        <?php if($submissions && $submissions->count() > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Student</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Status</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Submitted</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Grade</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $submissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-900"><?php echo e($submission->user->name); ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded 
                                    <?php echo e($submission->status === 'submitted' ? 'bg-green-100 text-green-800' : ''); ?>

                                    <?php echo e($submission->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : ''); ?>

                                    <?php echo e($submission->is_late ? 'bg-red-100 text-red-800' : ''); ?>

                                ">
                                    <?php echo e($submission->is_late ? 'Late' : ucfirst($submission->status)); ?>

                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <?php echo e($submission->submitted_at?->format('M d, H:i') ?? 'Not submitted'); ?>

                            </td>
                            <td class="px-4 py-3 font-semibold text-gray-900">
                                <?php echo e($submission->grade?->score ?? '-'); ?>/<?php echo e($task->max_score); ?>

                            </td>
                            <td class="px-4 py-3">
                                <a href="<?php echo e(route('submissions.review', $submission)); ?>"
                                   class="text-blue-600 hover:underline text-sm">Review</a>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-center py-8">No submissions yet.</p>
        <?php endif; ?>
    </div>
    </div>

    <!-- Non-Submitters List -->
    <?php if(isset($nonSubmitters) && $nonSubmitters->count() > 0): ?>
        <div class="bg-white rounded-lg shadow p-6 mt-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Students Not Submitted</h2>
            <p class="text-sm text-gray-600 mb-4"><?php echo e($nonSubmitters->count()); ?> enrolled students have not submitted this assignment.</p>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Student ID</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Name</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Email</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $nonSubmitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $student): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-900"><?php echo e($student->student_id ?? 'N/A'); ?></td>
                                <td class="px-4 py-3 text-gray-900"><?php echo e($student->first_name); ?> <?php echo e($student->last_name); ?></td>
                                <td class="px-4 py-3 text-gray-600"><?php echo e($student->email); ?></td>
                                <td class="px-4 py-3">
                                    <a href="mailto:<?php echo e($student->email); ?>" class="text-blue-600 hover:underline text-sm">Send Reminder</a>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
            <?php if($nonSubmitters->hasPages()): ?>
                <div class="mt-5"><?php echo e($nonSubmitters->links()); ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/submission-tasks/show.blade.php ENDPATH**/ ?>