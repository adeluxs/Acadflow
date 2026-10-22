<?php $__env->startSection('content'); ?>
<div class="min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white shadow sm:rounded-lg">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900"><?php echo e($submission->title); ?></h1>
                        <p class="text-gray-600 mt-1">
                            By <?php echo e($submission->user->first_name); ?> <?php echo e($submission->user->last_name); ?> | 
                            <?php echo e($submission->course->code); ?> - <?php echo e($submission->course->name); ?>

                        </p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-sm font-semibold 
                        <?php if($submission->status === 'submitted'): ?> bg-yellow-100 text-yellow-800
                        <?php elseif($submission->status === 'graded'): ?> bg-green-100 text-green-800
                        <?php elseif($submission->status === 'under_review'): ?> bg-blue-100 text-blue-800
                        <?php else: ?> bg-gray-100 text-gray-800 <?php endif; ?>">
                        <?php echo e(ucfirst(str_replace('_', ' ', $submission->status))); ?>

                    </span>
                </div>
            </div>

            <!-- Submission Details -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Submitted</p>
                        <p class="text-gray-900 font-medium"><?php echo e($submission->submitted_at?->format('M d, Y H:i') ?? 'Not submitted'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Type</p>
                        <p class="text-gray-900 font-medium"><?php echo e(ucfirst($submission->type)); ?></p>
                    </div>
                </div>
            </div>

            <!-- Submission Content -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 mb-3">Submission Files</h2>
                <?php if($submission->versions->isNotEmpty()): ?>
                    <div class="space-y-2">
                        <?php $__currentLoopData = $submission->versions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $version): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo e($version->file_name); ?></p>
                                    <p class="text-sm text-gray-600">v<?php echo e($version->version_number); ?> • <?php echo e(number_format($version->file_size / 1024, 2)); ?> KB • <?php echo e($version->created_at?->format('M d, Y H:i') ?? 'Unknown'); ?></p>
                                </div>
                                <a href="<?php echo e(route('submission-versions.download', ['submission' => $submission, 'version' => $version->id])); ?>" 
                                   class="px-3 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm">
                                    Download
                                </a>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500">No files uploaded.</p>
                <?php endif; ?>
            </div>

            <!-- Written submission (for text / both formats) -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 mb-3">Written submission</h2>
                <?php ($reviewText = \App\Support\HtmlSanitizer::toPlainText($submission->description)); ?>
                <?php if($reviewText !== ''): ?>
                    <div class="prose prose-sm max-w-none rounded-lg border border-slate-200 bg-slate-50/60 p-4 text-gray-800"><?php echo $submission->description; ?></div>
                <?php else: ?>
                    <p class="text-gray-500">No written answer.</p>
                <?php endif; ?>
            </div>

            <?php if($submission->type === 'project'): ?>
                <div class="px-6 py-5 border-b border-gray-200">
                    <?php echo $__env->make('ai._contextual-assistant', [
                        'assistantFeature' => 'project_assistant',
                        'assistantEndpoint' => route('ai.context.project', $submission),
                    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </div>
            <?php endif; ?>

            <!-- Grading Section -->
            <?php if(!$submission->grade): ?>
                <?php ($reviewTask = $submission->task); ?>
                <?php ($reviewRubric = $reviewTask?->rubric); ?>
                <?php ($reviewMax = $reviewTask?->max_score ? (float) $reviewTask->max_score : 100); ?>
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Grade Submission</h2>
                    <form action="<?php echo e(route('submissions.grade', $submission)); ?>" method="POST" class="space-y-4">
                        <?php echo csrf_field(); ?>

                        <?php if($reviewRubric && is_array($reviewRubric->criteria) && count($reviewRubric->criteria)): ?>
                            <div class="rounded-md border border-indigo-100 bg-indigo-50/50 p-4">
                                <p class="text-sm font-semibold text-indigo-900 mb-2">Rubric: <?php echo e($reviewRubric->name); ?></p>
                                <div class="space-y-2">
                                    <?php $__currentLoopData = $reviewRubric->criteria; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $criterion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php ($cName = is_array($criterion) ? ($criterion['name'] ?? $criterion['key'] ?? ('Criterion '.($index + 1))) : $criterion); ?>
                                        <?php ($cMax = is_array($criterion) ? ($criterion['points'] ?? $criterion['max_points'] ?? $criterion['weight'] ?? null) : null); ?>
                                        <label class="flex items-center justify-between gap-3 text-sm text-gray-700">
                                            <span><?php echo e($cName); ?> <?php if($cMax): ?><span class="text-xs text-gray-400">/ <?php echo e($cMax); ?></span><?php endif; ?></span>
                                            <input type="number" step="0.01" min="0" <?php if($cMax): ?> max="<?php echo e($cMax); ?>" <?php endif; ?> name="criteria[<?php echo e($cName); ?>]" class="w-28 rounded-md border-gray-300 text-sm">
                                        </label>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div>
                            <label for="score" class="block text-sm font-medium text-gray-700">Score (0-<?php echo e($reviewMax); ?>)</label>
                            <input type="number" name="score" id="score" min="0" max="<?php echo e($reviewMax); ?>" step="0.01" <?php if(!$reviewRubric): ?> required <?php endif; ?>
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <?php if($reviewRubric): ?>
                                <p class="mt-1 text-xs text-gray-500">Leave the score blank to use the rubric total.</p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label for="feedback" class="block text-sm font-medium text-gray-700">Feedback</label>
                            <textarea name="feedback" id="feedback" rows="4"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                            Submit Grade
                        </button>
                    </form>

                    
                    <?php if(Route::has('submissions.ai.grade-suggestion')): ?>
                        <div class="mt-4 rounded-md border border-purple-200 bg-purple-50 p-3 text-xs text-purple-900">
                            <button type="button" id="aiGradeSuggestion" data-endpoint="<?php echo e(route('submissions.ai.grade-suggestion', $submission)); ?>" class="rounded-md bg-purple-600 px-3 py-1.5 font-bold text-white hover:bg-purple-700">Suggest a grade (AI, advisory)</button>
                            <p id="aiGradeSuggestionOutput" class="mt-2 hidden"></p>
                        </div>
                        <script>
                        (() => {
                            const btn = document.getElementById('aiGradeSuggestion');
                            const out = document.getElementById('aiGradeSuggestionOutput');
                            if (!btn || !out) return;
                            btn.addEventListener('click', async () => {
                                btn.disabled = true;
                                out.classList.remove('hidden');
                                out.textContent = 'Analysing…';
                                try {
                                    const res = await fetch(btn.dataset.endpoint, {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>', 'Accept': 'application/json' },
                                    });
                                    const json = await res.json();
                                    out.textContent = json.message || json.summary || JSON.stringify(json);
                                } catch (e) {
                                    out.textContent = 'Could not fetch a suggestion right now.';
                                } finally {
                                    btn.disabled = false;
                                }
                            });
                        })();
                        </script>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Grade</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Score</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo e($submission->grade->score); ?>/<?php echo e($submission->grade->max_score ?? 100); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Feedback</p>
                            <p class="text-gray-900"><?php echo e($submission->grade->feedback ?? 'No feedback provided'); ?></p>
                        </div>
                    </div>
                    <?php if(is_array($submission->grade->criteria_scores) && count($submission->grade->criteria_scores)): ?>
                        <div class="mt-4">
                            <p class="text-sm font-semibold text-gray-700 mb-2">Rubric breakdown</p>
                            <ul class="space-y-1 text-sm text-gray-700">
                                <?php $__currentLoopData = $submission->grade->criteria_scores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $criterion => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <li class="flex justify-between"><span><?php echo e($criterion); ?></span><span class="font-semibold"><?php echo e($value); ?></span></li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Comments Section -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Comments</h2>
                
                <div class="space-y-4 mb-6">
                    <?php $__empty_0 = true; $__currentLoopData = $submission->comments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                        <div class="p-3 bg-gray-50 rounded">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo e($comment->user->first_name); ?> <?php echo e($comment->user->last_name); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo e($comment->created_at->format('M d, Y H:i')); ?></p>
                                </div>
                                <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded"><?php echo e(ucfirst($comment->type)); ?></span>
                            </div>
                            <p class="text-gray-700 mt-2"><?php echo e($comment->content); ?></p>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                        <p class="text-gray-500">No comments yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Add Comment Form -->
                <form action="<?php echo e(route('submissions.comment', $submission)); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <textarea name="comment" rows="3" placeholder="Add a comment..." required
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    <button type="submit" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Add Comment
                    </button>
                </form>
            </div>

            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Compare Versions</h2>
                <form action="<?php echo e(route('submissions.compare', $submission)); ?>" method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Version A</label>
                            <select name="version_a" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Select version</option>
                                <?php $__currentLoopData = $submission->versions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $version): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($version->id); ?>">v<?php echo e($version->version_number); ?> — <?php echo e($version->file_name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Version B</label>
                            <select name="version_b" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Select version</option>
                                <?php $__currentLoopData = $submission->versions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $version): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($version->id); ?>">v<?php echo e($version->version_number); ?> — <?php echo e($version->file_name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                        Compare Versions
                    </button>
                </form>
            </div>

            <!-- Actions -->
            <div class="px-6 py-4 bg-gray-50 flex flex-wrap gap-3">
                <form action="<?php echo e(route('submissions.approve', $submission)); ?>" method="POST" class="inline">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        Approve Submission
                    </button>
                </form>
                <form action="<?php echo e(route('submissions.request-correction', $submission)); ?>" method="POST" class="inline">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700">
                        Request Correction
                    </button>
                </form>
                <form action="<?php echo e(route('submissions.reject', $submission)); ?>" method="POST" class="inline">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        Reject Submission
                    </button>
                </form>
                <a href="<?php echo e(route('submissions.lecturer-index')); ?>" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                    Back to List
                </a>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/submissions/review.blade.php ENDPATH**/ ?>