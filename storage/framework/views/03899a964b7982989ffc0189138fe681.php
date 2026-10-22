<?php $__env->startSection('title', 'Submit Assignment'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <!-- Breadcrumb -->
    <div class="mb-6 text-sm">
        <?php if(isset($task) && $task->course): ?>
            <a href="<?php echo e(route('courses.show', $task->course)); ?>" class="text-blue-600 hover:underline"><?php echo e($task->course->name); ?></a>
            <span class="text-gray-600 mx-2">/</span>
            <a href="<?php echo e(route('courses.assignments', $task->course)); ?>" class="text-blue-600 hover:underline">Assignments</a>
            <span class="text-gray-600 mx-2">/</span>
            <a href="<?php echo e(route('submission-tasks.student.show', ['course' => $task->course ?? $task->course_id, 'task' => $task])); ?>" class="text-blue-600 hover:underline"><?php echo e($task->title); ?></a>
        <?php else: ?>
            Assignments
        <?php endif; ?>
    </div>

    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Submit Assignment</h1>
        <p class="text-gray-600 mt-2"><?php echo e($task?->title ?? 'Choose an available assignment to continue.'); ?></p>
    </div>

    <?php if(!$task): ?>
        <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Available assignments</h2>
            <p class="mt-1 text-sm text-slate-500">Only published assignments from courses you are actively enrolled in are shown.</p>
            <div class="mt-5 space-y-3">
                <?php $__empty_0 = true; $__currentLoopData = $availableTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $availableTask): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                    <a href="<?php echo e(route('submissions.create', ['task_id' => $availableTask->id])); ?>" class="flex items-center justify-between rounded-xl border border-slate-200 p-4 transition hover:border-indigo-400 hover:bg-indigo-50/40">
                        <div>
                            <div class="font-semibold text-slate-900"><?php echo e($availableTask->title); ?></div>
                            <div class="mt-1 text-xs text-slate-500"><?php echo e($availableTask->course?->code); ?> · <?php echo e($availableTask->course?->name); ?></div>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            <div><?php echo e(($availableTask->acceptanceDeadline(auth()->user()) ?? $availableTask->due_date)?->format('M d, Y') ?? 'No due date'); ?></div>
                            <span class="font-semibold text-indigo-600">Open →</span>
                        </div>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                    <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
                        No published assignments are available for your active courses yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>

    <?php
        // Accurate, reassuring status. The acceptance deadline already accounts
        // for the late window and any approved per-student extension, so an
        // extended deadline never renders as "closed".
        $acceptance = $task->acceptanceDeadline(auth()->user());
        $baseDeadline = $task->due_date ?? $task->close_at ?? $task->late_deadline;
        $deadlineExtended = $acceptance && $baseDeadline && $acceptance->gt($baseDeadline);
        $windowClosed = $acceptance ? now()->isAfter($acceptance) : false;
        $lateWindow = ! $windowClosed && $task->due_date && now()->isAfter($task->due_date);
        $acceptsText = $task->acceptsText();
        $acceptsFiles = $task->acceptsFiles();
    ?>

    
    <div class="mb-6 rounded-2xl border p-4 text-sm
        <?php if($windowClosed): ?> border-rose-200 bg-rose-50 text-rose-800
        <?php elseif($deadlineExtended): ?> border-indigo-200 bg-indigo-50 text-indigo-900
        <?php elseif($lateWindow): ?> border-amber-200 bg-amber-50 text-amber-900
        <?php else: ?> border-emerald-200 bg-emerald-50 text-emerald-800 <?php endif; ?>">
        <?php if($windowClosed): ?>
            <p class="font-bold">This assignment is closed.</p>
            <p class="mt-1">The submission window ended on <?php echo e($acceptance?->format('M d, Y H:i')); ?> and no further submissions are accepted.</p>
        <?php elseif($deadlineExtended): ?>
            <p class="font-bold">Submission window open — deadline extended.</p>
            <p class="mt-1">Your lecturer extended the deadline to <strong><?php echo e($acceptance?->format('M d, Y H:i')); ?></strong>, so you can still submit this assignment. The original deadline was <?php echo e($baseDeadline?->format('M d, Y H:i')); ?>.</p>
        <?php elseif($lateWindow): ?>
            <p class="font-bold">Late submission window is open.</p>
            <p class="mt-1">The original deadline passed on <?php echo e($task->due_date?->format('M d, Y H:i')); ?>. You can still submit until <?php echo e($acceptance?->format('M d, Y H:i')); ?> — a <?php echo e($task->late_submission_penalty_percent); ?>% late penalty applies.</p>
        <?php else: ?>
            <p class="font-bold">Submission window open.</p>
            <p class="mt-1">Submit before <strong><?php echo e(($acceptance ?? $baseDeadline)?->format('M d, Y H:i')); ?></strong>.</p>
        <?php endif; ?>
    </div>

    <?php if($errors->any()): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <h3 class="font-bold text-red-900 mb-2">Please fix the following errors:</h3>
            <ul class="text-red-700 text-sm space-y-1">
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li>• <?php echo e($error); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-3 gap-8">
        <!-- Left: Upload Form -->
        <div class="col-span-2">
    <form action="<?php echo e(route('submissions.store')); ?>" method="POST" enctype="multipart/form-data" id="submissionForm" data-format="<?php echo e($task->submission_format ?? 'file'); ?>">
        <?php echo csrf_field(); ?>
        <?php if(isset($task) && $task->course): ?>
            <input type="hidden" name="course_id" value="<?php echo e($task->course->id); ?>">
        <?php endif; ?>
        <input type="hidden" name="submission_task_id" value="<?php echo e($task->id ?? ''); ?>">
        <input type="hidden" name="type" value="<?php echo e($task->type ?? ''); ?>">
        <input type="hidden" name="title" value="<?php echo e($task->title ?? ''); ?>">
        
        <input type="hidden" name="intent" value="submit">
        

                <?php if($acceptsText): ?>
                <!-- Written Answer -->
                <div class="bg-white rounded-lg shadow p-8 mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-2">✍️ Your Written Answer</h2>
                    <p class="text-sm text-gray-600 mb-4">Write your submission below. Use the toolbar to format headings, lists, links and emphasis — your answer is saved with the submission.</p>
                    <label for="submissionContent" class="sr-only">Your written answer</label>
                    <textarea id="submissionContent" name="content" rows="16" data-rich-editor data-editor-height="420px"><?php echo e(old('content')); ?></textarea>
                    <?php $__errorArgs = ['content'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-red-600 text-sm mt-2"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <?php endif; ?>

                <?php if($acceptsFiles): ?>
                <!-- Files Upload -->
                <div class="bg-white rounded-lg shadow p-8 mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">📁 Upload Your Files</h2>

                    <!-- File Input Area -->
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center mb-6 cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition"
                         onclick="document.getElementById('fileInput').click()">
                        <input type="file" id="fileInput" name="files[]" multiple 
                                class="hidden" accept="<?php echo e(implode(',', $task->allowed_file_types ?? [])); ?>"
                               onchange="handleFileSelect(event)">
                        
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v28a4 4 0 004 4h24a4 4 0 004-4V20m-8-12v8h8m-8-8L20 4"></path>
                        </svg>
                        
                        <p class="text-xl font-semibold text-gray-900 mb-1">Drag files here or click to browse</p>
                        <p class="text-gray-600 text-sm">
                             Accepted formats: <?php echo e(strtoupper(implode(', ', $task->allowed_file_types ?? []))); ?>

                        </p>
                        <p class="text-gray-500 text-sm mt-2">
                            Max <?php echo e($task->max_file_count); ?> files, <?php echo e($task->max_file_size_mb); ?>MB each
                        </p>
                    </div>

                    <!-- File Requirements Info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <p class="text-sm text-gray-700 mb-2">
                            <strong>Requirements:</strong>
                        </p>
                        <ul class="text-sm text-gray-700 space-y-1 ml-4">
                            <li>✓ Submit <?php echo e($task->min_file_count); ?> to <?php echo e($task->max_file_count); ?> files</li>
                            <li>✓ Each file max <?php echo e($task->max_file_size_mb); ?>MB</li>
                             <li>✓ Allowed types: <?php echo e(implode(', ', $task->allowed_file_types ?? [])); ?></li>
                        </ul>
                    </div>

                    <!-- Files List -->
                    <div id="filesList" class="space-y-2 mb-6">
                        <!-- Files will be added here dynamically -->
                    </div>

                    <?php $__errorArgs = ['files'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-red-600 text-sm mb-4"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <?php endif; ?>

                <!-- Comments -->
                <div class="bg-white rounded-lg shadow p-8 mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">💬 Submission Notes (Optional)</h2>

                    <textarea name="submission_notes" rows="6"
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Add any notes or comments for your instructor..."><?php echo e(old('submission_notes')); ?></textarea>

                    <p class="text-gray-500 text-sm mt-2">
                        You can use this space to explain your work, mention any issues you encountered, or provide additional context.
                    </p>

                    <?php $__errorArgs = ['submission_notes'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-red-600 text-sm mt-2"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <!-- Confirmation -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="confirm_submission" value="1" class="mt-1"
                               <?php echo e(old('confirm_submission') ? 'checked' : ''); ?>>
                        <span class="text-sm text-gray-700">
                            <strong>I confirm that:</strong>
                            <ul class="mt-2 space-y-1 ml-3">
                                <li>✓ This work is my own (or properly attributed group work)</li>
                                <li>✓ I have not plagiarized any content</li>
                                <li>✓ All files are in the correct format</li>
                                <li>✓ I understand the late submission penalty (if applicable)</li>
                            </ul>
                        </span>
                    </label>
                    <?php $__errorArgs = ['confirm_submission'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-red-600 text-sm mt-2"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-bold text-lg"
                            id="submitBtn" disabled>
                        📤 Submit Assignment
                    </button>
                    
                    <a href="<?php echo e(route('submission-tasks.student.show', ['course' => $task->course ?? $task->course_id, 'task' => $task])); ?>" 
                       class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold text-center">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Right: Sidebar -->
        <div>
            <!-- Deadline -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="font-bold text-gray-900 mb-4">📅 Deadline</h3>
                <p class="text-lg font-bold text-gray-900"><?php echo e(($acceptance ?? $baseDeadline)?->format('M d, Y') ?? 'No deadline set'); ?></p>
                <?php if($deadlineExtended): ?>
                    <p class="mt-1 text-xs font-semibold text-indigo-600">Extended by your lecturer</p>
                <?php endif; ?>
                <p class="text-sm text-gray-600"><?php echo e(($acceptance ?? $baseDeadline)?->format('H:i') ?? '—'); ?></p>

                <?php if($windowClosed): ?>
                    <div class="mt-4 p-3 bg-rose-50 border border-rose-200 rounded">
                        <p class="text-rose-700 font-semibold text-sm">Closed</p>
                        <p class="text-rose-600 text-xs mt-1">Submissions are no longer accepted for this assignment.</p>
                    </div>
                <?php elseif($deadlineExtended): ?>
                    <div class="mt-4 p-3 bg-indigo-50 border border-indigo-200 rounded">
                        <p class="text-indigo-700 font-semibold text-sm">Extended — you can still submit</p>
                        <p class="text-indigo-600 text-xs mt-1">New deadline: <?php echo e($acceptance?->format('M d, Y H:i')); ?>.</p>
                    </div>
                <?php elseif($lateWindow): ?>
                    <div class="mt-4 p-3 bg-orange-50 border border-orange-200 rounded">
                        <p class="text-orange-700 font-semibold text-sm">Late submission window</p>
                        <p class="text-orange-600 text-xs mt-1"><?php echo e($task->late_submission_penalty_percent); ?>% penalty will be applied to your grade.</p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 p-3 bg-emerald-50 border border-emerald-200 rounded">
                        <p class="text-emerald-700 font-semibold text-sm">Open</p>
                        <p class="text-emerald-600 text-xs mt-1">Submit before the deadline shown above.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Assignment Info -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="font-bold text-gray-900 mb-4">📋 Assignment Info</h3>
                <ul class="space-y-3 text-sm">
                    <li>
                        <p class="text-gray-600">Type</p>
                        <p class="font-semibold text-gray-900"><?php echo e(ucfirst($task->type)); ?> <?php echo e(ucfirst($task->title)); ?></p>
                    </li>
                    <li>
                        <p class="text-gray-600">Points</p>
                        <p class="font-semibold text-gray-900"><?php echo e($task->max_score); ?></p>
                    </li>
                    <li>
                        <p class="text-gray-600">Resubmissions Left</p>
                        <p class="font-semibold text-gray-900"><?php echo e($resubmitsLeft ?? '∞'); ?></p>
                    </li>
                </ul>
            </div>

            <!-- Tips -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h3 class="font-bold text-gray-900 mb-4">💡 Tips</h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li>✓ Check file sizes before uploading</li>
                    <li>✓ Make sure files are in the correct format</li>
                    <li>✓ Test your files can be opened</li>
                    <li>✓ Submit early to avoid connection issues</li>
                    <li>✓ You can check your submission after upload</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Track selected files
let selectedFiles = [];

// File input handler
function handleFileSelect(event) {
    const input = event.target;
    selectedFiles = Array.from(input.files);
    updateFilesList();
    validateFiles();
}

// Update files display
function updateFilesList() {
    const list = document.getElementById('filesList');
    list.innerHTML = '';

    if (selectedFiles.length === 0) {
        list.innerHTML = '<p class="text-gray-500 text-sm text-center py-4">No files selected</p>';
        return;
    }

    selectedFiles.forEach((file, index) => {
        const size = (file.size / 1024 / 1024).toFixed(2);
        const isValid = validateFile(file);
        
        const item = document.createElement('div');
        item.className = `flex justify-between items-center p-3 rounded border ${isValid ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}`;
        item.innerHTML = `
            <div class="flex-1">
                <p class="font-semibold text-gray-900">${file.name}</p>
                <p class="text-xs text-gray-600">${size}MB</p>
                ${!isValid ? '<p class="text-xs text-red-600 mt-1">Invalid file</p>' : ''}
            </div>
            <button type="button" onclick="removeFile(${index})" class="text-red-600 hover:underline text-sm">
                Remove
            </button>
        `;
        list.appendChild(item);
    });

    // Create a DataTransfer object and set files
    const dataTransfer = new DataTransfer();
    selectedFiles.forEach(file => dataTransfer.items.add(file));
    document.getElementById('fileInput').files = dataTransfer.files;
}

// Remove file
function removeFile(index) {
    selectedFiles.splice(index, 1);
    updateFilesList();
    validateFiles();
}

// Validate single file
function validateFile(file) {
    const maxSize = <?php echo e($task->max_file_size_mb); ?> * 1024 * 1024;
     const allowedTypes = <?php echo json_encode($task->allowed_file_types ?? []); ?>;
    const ext = file.name.split('.').pop().toLowerCase();
    
    return file.size <= maxSize && allowedTypes.includes(ext);
}

// Whether a written answer has been provided (the rich editor keeps the plain
// textarea in sync, so reading its value works either way).
function hasWrittenAnswer() {
    const el = document.getElementById('submissionContent');
    if (!el) return false;
    const tmp = document.createElement('div');
    tmp.innerHTML = el.value || '';
    return (tmp.textContent || '').trim().length > 0;
}

// Validate the submission against the assignment's format.
function validateFiles() {
    const format = document.getElementById('submissionForm').dataset.format || 'file';
    const minFiles = <?php echo e($task->min_file_count); ?>;
    const maxFiles = <?php echo e($task->max_file_count); ?>;

    const filesValid = selectedFiles.length <= maxFiles && selectedFiles.every(f => validateFile(f));
    const textOk = hasWrittenAnswer();

    let contentOk;
    if (format === 'text') {
        contentOk = textOk;
    } else if (format === 'both') {
        contentOk = textOk || (selectedFiles.length > 0 && filesValid);
    } else {
        contentOk = selectedFiles.length >= minFiles && filesValid;
    }

    const confirmed = document.querySelector('input[name="confirm_submission"]').checked;
    document.getElementById('submitBtn').disabled = !(contentOk && confirmed);
}

// Re-evaluate whenever the confirmation or the written answer changes.
document.querySelector('input[name="confirm_submission"]').addEventListener('change', validateFiles);
document.getElementById('submissionContent')?.addEventListener('input', validateFiles);
document.getElementById('submissionContent')?.addEventListener('change', validateFiles);
validateFiles();

// Drag and drop (only present when the assignment accepts file uploads).
const dropZone = document.querySelector('.border-dashed');

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function highlight(e) {
    dropZone.classList.add('border-blue-400', 'bg-blue-50');
}

function unhighlight(e) {
    dropZone.classList.remove('border-blue-400', 'bg-blue-50');
}

if (dropZone) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });
    dropZone.addEventListener('drop', handleDrop, false);
}

        // Offline submission support
        const taskId = <?php echo e($task->id); ?>;
        const courseId = <?php echo e($task->course->id); ?>;
        
        // Load saved draft
        async function loadDraft() {
            if (!window.syncManager) return;
            
            const draft = await window.syncManager.loadDraft('submission', taskId);
            if (draft && draft.files) {
                // Restore files if possible
                if (draft.submission_notes) {
                    document.querySelector('[name="submission_notes"]').value = draft.submission_notes;
                }
                
                // Show notification
                showDraftNotification(draft);
            }
        }
        
        function showDraftNotification(draft) {
            const notification = document.createElement('div');
            notification.className = 'mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg';
            notification.innerHTML = `
                <div class="flex justify-between items-start">
                    <div>
                        <p class="font-bold text-blue-900">Draft restored</p>
                        <p class="text-sm text-blue-700">Saved ${new Date(draft.timestamp).toLocaleString()}</p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-blue-600 hover:text-blue-800">
                        ✕
                    </button>
                </div>
            `;
            document.querySelector('form').prepend(notification);
        }
        
        // Save draft periodically
        let draftTimer;
        function saveDraft() {
            clearTimeout(draftTimer);
            draftTimer = setTimeout(async () => {
                if (!window.syncManager) return;
                
                const formData = {
                    submission_notes: document.querySelector('[name="submission_notes"]')?.value || '',
                    files: selectedFiles.map(f => f.name),
                    timestamp: Date.now(),
                };
                
                await window.syncManager.saveDraft('submission', taskId, formData);
                console.log('Draft saved');
            }, 2000);
        }
        
        // Monitor form changes
        document.querySelector('[name="submission_notes"]')?.addEventListener('input', saveDraft);
        
        // Override form submission for offline support
        document.getElementById('submissionForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            
            // Check if online
            if (!navigator.onLine) {
                // Queue for sync
                const formData = {
                    course_id: courseId,
                    submission_task_id: taskId,
                    submission_notes: document.querySelector('[name="submission_notes"]')?.value || '',
                    files: selectedFiles.map(f => ({ name: f.name, size: f.size })),
                };
                
                await window.syncManager.queueAction(
                    `/courses/${courseId}/assignments/${taskId}/submit`,
                    'POST',
                    formData
                );
                
                // Show offline confirmation
                showOfflineConfirmation();
                submitBtn.disabled = false;
                return;
            }
            
            // Online - submit normally
            submitBtn.textContent = 'Submitting...';
            e.target.submit();
        });
        
        function showOfflineConfirmation() {
            const confirmation = document.createElement('div');
            confirmation.className = 'mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg';
            confirmation.innerHTML = `
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div>
                        <p class="font-bold text-yellow-900">Submission Queued</p>
                        <p class="text-sm text-yellow-700">Your submission will be sent when you're back online.</p>
                    </div>
                </div>
            `;
            document.querySelector('form').prepend(confirmation);
        }
        
        // Load draft on page load
        if (window.syncManager) {
            loadDraft();
        }

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('fileInput').files = files;
            handleFileSelect({ target: { files } });
        }
    </script>
    <?php endif; ?>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/submissions/create.blade.php ENDPATH**/ ?>