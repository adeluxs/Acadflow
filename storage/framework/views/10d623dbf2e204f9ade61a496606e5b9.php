<!-- Assignment Title -->
<div class="mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">Assignment Title *</label>
    <input type="text" name="title" value="<?php echo e(old('title', $task->title ?? '')); ?>" 
           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
           placeholder="e.g., Midterm Project, Lab Report" required>
    <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
</div>

<!-- Description -->
<div class="mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">Description *</label>
    <textarea name="description" rows="4" 
              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Provide assignment overview and context..." required><?php echo e(old('description', $task->description ?? '')); ?></textarea>
    <?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
</div>

<!-- Instructions -->
<div class="mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">Instructions *</label>
    <textarea name="instructions" rows="6" 
              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm"
              placeholder="Step-by-step instructions for students..." required><?php echo e(old('instructions', $task->instructions ?? '')); ?></textarea>
    <?php $__errorArgs = ['instructions'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
</div>

<!-- Assignment Type -->
<?php ($typeProfiles = $typeProfiles ?? \App\Services\Assignments\AssignmentTypeProfile::availableDefinitions()); ?>
<div class="mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">Assignment Type *</label>
    <select name="type" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
        <option value="">Select type</option>
        <?php $__currentLoopData = $typeProfiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $typeKey => $typeDef): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($typeKey); ?>" <?php if(old('type', $task->type ?? '') === $typeKey): echo 'selected'; endif; ?>><?php echo e($typeDef['label']); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <?php $__errorArgs = ['type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
</div>

<!-- Type-specific fields: the assignment type drives a unique creation form -->
<?php ($typeProfiles = $typeProfiles ?? \App\Services\Assignments\AssignmentTypeProfile::definitions()); ?>
<?php ($currentType = old('type', $task->type ?? '')); ?>
<?php ($existingMeta = old('type_metadata', $task->type_metadata ?? [])); ?>
<?php $__currentLoopData = $typeProfiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $typeKey => $profile): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php if(!empty($profile['fields'])): ?>
        <div class="mb-6 rounded-lg border border-blue-100 bg-blue-50/50 p-4" data-type-fields="<?php echo e($typeKey); ?>" <?php if($currentType !== $typeKey): ?> style="display:none" <?php endif; ?>>
            <p class="text-sm font-bold text-gray-800"><?php echo e($profile['label']); ?> details</p>
            <p class="text-xs text-gray-500 mb-3"><?php echo e($profile['description']); ?></p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php $__currentLoopData = $profile['fields']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php ($fname = 'type_metadata['.$field['key'].']'); ?>
                    <?php ($fvalue = $existingMeta[$field['key']] ?? ''); ?>
                    <div class="<?php echo e($field['type'] === 'textarea' ? 'md:col-span-2' : ''); ?>">
                        <?php if($field['type'] === 'checkbox'): ?>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="<?php echo e($fname); ?>" value="1" <?php if((bool) $fvalue): echo 'checked'; endif; ?> class="rounded border-gray-300">
                                <?php echo e($field['label']); ?>

                            </label>
                        <?php else: ?>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                <?php echo e($field['label']); ?> <?php if($field['required']): ?>*<?php endif; ?>
                            </label>
                            <?php if($field['type'] === 'textarea'): ?>
                                <textarea name="<?php echo e($fname); ?>" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"><?php echo e($fvalue); ?></textarea>
                            <?php elseif($field['type'] === 'select'): ?>
                                <select name="<?php echo e($fname); ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select</option>
                                    <?php $__currentLoopData = $field['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $optValue => $optLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($optValue); ?>" <?php if((string) $fvalue === (string) $optValue): echo 'selected'; endif; ?>><?php echo e($optLabel); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            <?php elseif($field['type'] === 'date'): ?>
                                <input type="date" name="<?php echo e($fname); ?>" value="<?php echo e($fvalue); ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <?php elseif($field['type'] === 'number'): ?>
                                <input type="number" name="<?php echo e($fname); ?>" value="<?php echo e($fvalue); ?>" min="1" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <?php else: ?>
                                <input type="<?php echo e($field['type'] === 'email' ? 'email' : 'text'); ?>" name="<?php echo e($fname); ?>" value="<?php echo e($fvalue); ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php $__errorArgs = ['type_metadata.'.$field['key']];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <span class="text-red-600 text-xs"><?php echo e($message); ?></span>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<script>
(() => {
    const select = document.querySelector('select[name="type"]');
    if (!select) return;
    const groups = document.querySelectorAll('[data-type-fields]');
    const sync = () => {
        groups.forEach((group) => {
            group.style.display = group.dataset.typeFields === select.value ? '' : 'none';
        });
    };
    select.addEventListener('change', sync);
    sync();
})();
</script>

<!-- Deadlines -->
<div class="grid grid-cols-2 gap-4 mb-6">
    <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Open Date & Time *</label>
        <input type="datetime-local" name="open_at" 
               value="<?php echo e(old('open_at', isset($task) && $task->open_at ? $task->open_at->format('Y-m-d\TH:i') : '')); ?>"
               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
        <?php $__errorArgs = ['open_at'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>
    
    <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Due Date & Time (Soft Deadline) *</label>
        <input type="datetime-local" name="due_date" 
               value="<?php echo e(old('due_date', isset($task) && $task->due_date ? $task->due_date->format('Y-m-d\TH:i') : '')); ?>"
               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
        <p class="text-xs text-gray-500 mt-1">Students see warnings near this time</p>
        <?php $__errorArgs = ['due_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>
</div>

<!-- Late Deadline -->
<div class="grid grid-cols-2 gap-4 mb-6">
    <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Hard Deadline (No More Submissions) *</label>
        <input type="datetime-local" name="late_deadline" 
               value="<?php echo e(old('late_deadline', isset($task) && $task->late_deadline ? $task->late_deadline->format('Y-m-d\TH:i') : '')); ?>"
               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
        <p class="text-xs text-gray-500 mt-1">Absolute cutoff for submissions</p>
        <?php $__errorArgs = ['late_deadline'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Close Date & Time</label>
        <input type="datetime-local" name="close_at" 
               value="<?php echo e(old('close_at', isset($task) && $task->close_at ? $task->close_at->format('Y-m-d\TH:i') : '')); ?>"
               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <p class="text-xs text-gray-500 mt-1">When assignment is archived (optional)</p>
    </div>
</div>

<!-- File Requirements (only relevant when the format accepts files) -->
<div class="bg-gray-50 p-6 rounded-lg mb-6" data-format-fields="file">
    <h3 class="font-semibold text-gray-900 mb-4">File Requirements</h3>
    
    <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Allowed File Types</label>
<input type="text" name="allowed_file_types" 
                    value="<?php echo e(old('allowed_file_types', isset($task) ? implode(', ', $task->allowed_file_types ?? []) : '')); ?>"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="pdf, docx, xlsx, txt (comma-separated)">
            <?php $__errorArgs = ['allowed_file_types'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Max File Size (MB) *</label>
            <input type="number" name="max_file_size_mb" value="<?php echo e(old('max_file_size_mb', $task->max_file_size_mb ?? 50)); ?>"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   min="1" max="500" required>
            <?php $__errorArgs = ['max_file_size_mb'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Min Files *</label>
            <input type="number" name="min_file_count" value="<?php echo e(old('min_file_count', $task->min_file_count ?? 1)); ?>"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   min="1" required>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Max Files *</label>
            <input type="number" name="max_file_count" value="<?php echo e(old('max_file_count', $task->max_file_count ?? 10)); ?>"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   min="1" required>
        </div>
    </div>
</div>

<!-- Submission Rules -->
<div class="bg-gray-50 p-6 rounded-lg mb-6">
    <h3 class="font-semibold text-gray-900 mb-4">Submission Rules</h3>
    
    <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Max Resubmissions *</label>
            <input type="number" name="max_resubmissions" 
                   value="<?php echo e(old('max_resubmissions', $task->max_resubmissions ?? 2)); ?>"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   min="0" required>
            <p class="text-xs text-gray-500 mt-1">0 = unlimited</p>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Late Submission Penalty (%) *</label>
            <input type="number" name="late_submission_penalty_percent" 
                   value="<?php echo e(old('late_submission_penalty_percent', $task->late_submission_penalty_percent ?? 10)); ?>"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   min="0" max="100" required>
            <p class="text-xs text-gray-500 mt-1">Deducted from score for late submissions</p>
        </div>
    </div>

    <div class="flex items-center gap-4">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="allow_late_submissions" value="1"
                   <?php echo e((old('allow_late_submissions', $task->allow_late_submissions ?? true)) ? 'checked' : ''); ?>>
            <span class="text-sm font-medium text-gray-700">Allow Late Submissions</span>
        </label>

        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="allow_group_submissions" value="1"
                   <?php echo e((old('allow_group_submissions', $task->allow_group_submissions ?? false)) ? 'checked' : ''); ?>>
            <span class="text-sm font-medium text-gray-700">Allow Group Submissions</span>
        </label>
    </div>
</div>

<!-- Group Settings (if enabled) -->
<div id="groupSettings" class="bg-gray-50 p-6 rounded-lg mb-6" style="display: none;">
    <h3 class="font-semibold text-gray-900 mb-4">Group Settings</h3>
    
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Min Group Size</label>
            <input type="number" name="min_group_size" 
                   value="<?php echo e(old('min_group_size', $task->min_group_size ?? 2)); ?>"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   min="2">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Max Group Size</label>
            <input type="number" name="max_group_size" 
                   value="<?php echo e(old('max_group_size', $task->max_group_size ?? 5)); ?>"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   min="2">
        </div>
    </div>
</div>

<!-- Visibility -->
<div class="mb-6">
    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_visible_to_students" value="1"
               <?php echo e((old('is_visible_to_students', isset($task) && $task->is_visible_to_students)) ? 'checked' : ''); ?>>
        <span class="text-sm font-medium text-gray-700">Visible to Students (after publishing)</span>
    </label>
</div>

<!-- Grading -->
<div class="mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">Max Score *</label>
    <input type="number" name="max_score" value="<?php echo e(old('max_score', $task->max_score ?? 100)); ?>"
           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
           min="0" required>
</div>

<!-- Semester -->
<div class="mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">Semester *</label>
    <select name="semester_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
        <option value="">Select Semester</option>
        <?php $__currentLoopData = $semesters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $semester): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($semester->id); ?>" <?php echo e((old('semester_id', $task->semester_id ?? '') == $semester->id) ? 'selected' : ''); ?>>
                <?php echo e($semester->name); ?> <?php echo e($semester->academic_year ? '('.$semester->academic_year.')' : ''); ?>

            </option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <?php $__errorArgs = ['semester_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
</div>

<!-- Submission Format -->
<div class="mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">Submission Format *</label>
    <select name="submission_format" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
        <option value="">Select format</option>
        <option value="file" <?php echo e((old('submission_format', $task->submission_format ?? '') === 'file') ? 'selected' : ''); ?>>File Upload</option>
        <option value="text" <?php echo e((old('submission_format', $task->submission_format ?? '') === 'text') ? 'selected' : ''); ?>>Text Entry</option>
        <option value="both" <?php echo e((old('submission_format', $task->submission_format ?? '') === 'both') ? 'selected' : ''); ?>>File or Text</option>
    </select>
    <?php $__errorArgs = ['submission_format'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
</div>

<!-- Text format guidance (shown when the format allows written answers) -->
<div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-6" data-format-fields="text">
    <h3 class="font-semibold text-gray-900 mb-1">Text submission</h3>
    <p class="text-sm text-gray-700">Students will write their answer in a rich text editor with formatting, lists and links. No file upload is required for a text-only assignment.</p>
</div>

<!-- Buttons -->
<div class="flex gap-3 pt-6 border-t">
    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
        <?php echo e(isset($task) ? 'Update Assignment' : 'Create Assignment'); ?>

    </button>
    
    <a href="<?php echo e(route('submission-tasks.manage.index', $course)); ?>" 
       class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
        Cancel
    </a>
</div>

<script>
// Show/hide group settings based on checkbox
document.querySelector('input[name="allow_group_submissions"]').addEventListener('change', function() {
    document.getElementById('groupSettings').style.display = this.checked ? 'block' : 'none';
});

// Show on page load if checked
if (document.querySelector('input[name="allow_group_submissions"]').checked) {
    document.getElementById('groupSettings').style.display = 'block';
}

// Reveal only the fields relevant to the selected submission format. Inputs are
// hidden (not removed), so any values already entered are preserved when the
// lecturer switches formats.
(() => {
    const formatSelect = document.querySelector('select[name="submission_format"]');
    if (!formatSelect) return;
    const groups = document.querySelectorAll('[data-format-fields]');
    const sync = () => {
        const format = formatSelect.value || 'file';
        groups.forEach((group) => {
            const belongs = group.dataset.formatFields;
            const show = belongs === 'file'
                ? (format === 'file' || format === 'both')
                : belongs === 'text'
                    ? (format === 'text' || format === 'both')
                    : false;
            group.style.display = show ? '' : 'none';
        });
    };
    formatSelect.addEventListener('change', sync);
    sync();
})();
</script>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/submission-tasks/_form.blade.php ENDPATH**/ ?>