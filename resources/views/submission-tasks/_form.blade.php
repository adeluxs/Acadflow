<!-- Assignment Title -->
<div class="mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">Assignment Title *</label>
    <input type="text" name="title" value="{{ old('title', $task->title ?? '') }}" 
           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
           placeholder="e.g., Midterm Project, Lab Report" required>
    @error('title')
        <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
    @enderror
</div>

<!-- Description -->
<div class="mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">Description *</label>
    <textarea name="description" rows="4" 
              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Provide assignment overview and context..." required>{{ old('description', $task->description ?? '') }}</textarea>
    @error('description')
        <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
    @enderror
</div>

<!-- Instructions -->
<div class="mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">Instructions *</label>
    <textarea name="instructions" rows="6" 
              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm"
              placeholder="Step-by-step instructions for students..." required>{{ old('instructions', $task->instructions ?? '') }}</textarea>
    @error('instructions')
        <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
    @enderror
</div>

<!-- Assignment Type -->
<div class="mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">Assignment Type *</label>
    <select name="type" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
        <option value="">Select type</option>
        <option value="assignment" {{ (old('type', $task->type ?? '') === 'assignment') ? 'selected' : '' }}>Assignment</option>
        <option value="project" {{ (old('type', $task->type ?? '') === 'project') ? 'selected' : '' }}>Project</option>
        <option value="siwes" {{ (old('type', $task->type ?? '') === 'siwes') ? 'selected' : '' }}>SIWES</option>
        <option value="group" {{ (old('type', $task->type ?? '') === 'group') ? 'selected' : '' }}>Group Work</option>
        <option value="seminar" {{ (old('type', $task->type ?? '') === 'seminar') ? 'selected' : '' }}>Seminar</option>
    </select>
    @error('type')
        <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
    @enderror
</div>

<!-- Deadlines -->
<div class="grid grid-cols-2 gap-4 mb-6">
    <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Open Date & Time *</label>
        <input type="datetime-local" name="open_at" 
               value="{{ old('open_at', isset($task) && $task->open_at ? $task->open_at->format('Y-m-d\TH:i') : '') }}"
               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
        @error('open_at')
            <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
        @enderror
    </div>
    
    <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Due Date & Time (Soft Deadline) *</label>
        <input type="datetime-local" name="due_date" 
               value="{{ old('due_date', isset($task) && $task->due_date ? $task->due_date->format('Y-m-d\TH:i') : '') }}"
               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
        <p class="text-xs text-gray-500 mt-1">Students see warnings near this time</p>
        @error('due_date')
            <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
        @enderror
    </div>
</div>

<!-- Late Deadline -->
<div class="grid grid-cols-2 gap-4 mb-6">
    <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Hard Deadline (No More Submissions) *</label>
        <input type="datetime-local" name="late_deadline" 
               value="{{ old('late_deadline', isset($task) && $task->late_deadline ? $task->late_deadline->format('Y-m-d\TH:i') : '') }}"
               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
        <p class="text-xs text-gray-500 mt-1">Absolute cutoff for submissions</p>
        @error('late_deadline')
            <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
        @enderror
    </div>

    <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Close Date & Time</label>
        <input type="datetime-local" name="close_at" 
               value="{{ old('close_at', isset($task) && $task->close_at ? $task->close_at->format('Y-m-d\TH:i') : '') }}"
               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <p class="text-xs text-gray-500 mt-1">When assignment is archived (optional)</p>
    </div>
</div>

<!-- File Requirements -->
<div class="bg-gray-50 p-6 rounded-lg mb-6">
    <h3 class="font-semibold text-gray-900 mb-4">File Requirements</h3>
    
    <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Allowed File Types</label>
<input type="text" name="allowed_file_types" 
                    value="{{ old('allowed_file_types', isset($task) ? implode(', ', $task->allowed_file_types ?? []) : '') }}"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="pdf, docx, xlsx, txt (comma-separated)">
            @error('allowed_file_types')
                <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Max File Size (MB) *</label>
            <input type="number" name="max_file_size_mb" value="{{ old('max_file_size_mb', $task->max_file_size_mb ?? 50) }}"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   min="1" max="500" required>
            @error('max_file_size_mb')
                <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Min Files *</label>
            <input type="number" name="min_file_count" value="{{ old('min_file_count', $task->min_file_count ?? 1) }}"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   min="1" required>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Max Files *</label>
            <input type="number" name="max_file_count" value="{{ old('max_file_count', $task->max_file_count ?? 10) }}"
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
                   value="{{ old('max_resubmissions', $task->max_resubmissions ?? 2) }}"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   min="0" required>
            <p class="text-xs text-gray-500 mt-1">0 = unlimited</p>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Late Submission Penalty (%) *</label>
            <input type="number" name="late_submission_penalty_percent" 
                   value="{{ old('late_submission_penalty_percent', $task->late_submission_penalty_percent ?? 10) }}"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   min="0" max="100" required>
            <p class="text-xs text-gray-500 mt-1">Deducted from score for late submissions</p>
        </div>
    </div>

    <div class="flex items-center gap-4">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="allow_late_submissions" value="1"
                   {{ (old('allow_late_submissions', $task->allow_late_submissions ?? true)) ? 'checked' : '' }}>
            <span class="text-sm font-medium text-gray-700">Allow Late Submissions</span>
        </label>

        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="allow_group_submissions" value="1"
                   {{ (old('allow_group_submissions', $task->allow_group_submissions ?? false)) ? 'checked' : '' }}>
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
                   value="{{ old('min_group_size', $task->min_group_size ?? 2) }}"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   min="2">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Max Group Size</label>
            <input type="number" name="max_group_size" 
                   value="{{ old('max_group_size', $task->max_group_size ?? 5) }}"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   min="2">
        </div>
    </div>
</div>

<!-- Visibility -->
<div class="mb-6">
    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_visible_to_students" value="1"
               {{ (old('is_visible_to_students', isset($task) && $task->is_visible_to_students)) ? 'checked' : '' }}>
        <span class="text-sm font-medium text-gray-700">Visible to Students (after publishing)</span>
    </label>
</div>

<!-- Grading -->
<div class="mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">Max Score *</label>
    <input type="number" name="max_score" value="{{ old('max_score', $task->max_score ?? 100) }}"
           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
           min="0" required>
</div>

<!-- Semester -->
<div class="mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">Semester *</label>
    <select name="semester_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
        <option value="">Select Semester</option>
        @foreach($semesters as $semester)
            <option value="{{ $semester->id }}" {{ (old('semester_id', $task->semester_id ?? '') == $semester->id) ? 'selected' : '' }}>
                {{ $semester->name }} {{ $semester->academic_year ? '('.$semester->academic_year.')' : '' }}
            </option>
        @endforeach
    </select>
    @error('semester_id')
        <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
    @enderror
</div>

<!-- Submission Format -->
<div class="mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">Submission Format *</label>
    <select name="submission_format" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
        <option value="">Select format</option>
        <option value="file" {{ (old('submission_format', $task->submission_format ?? '') === 'file') ? 'selected' : '' }}>File Upload</option>
        <option value="text" {{ (old('submission_format', $task->submission_format ?? '') === 'text') ? 'selected' : '' }}>Text Entry</option>
        <option value="both" {{ (old('submission_format', $task->submission_format ?? '') === 'both') ? 'selected' : '' }}>File or Text</option>
    </select>
    @error('submission_format')
        <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
    @enderror
</div>

<!-- Buttons -->
<div class="flex gap-3 pt-6 border-t">
    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
        {{ isset($task) ? 'Update Assignment' : 'Create Assignment' }}
    </button>
    
    <a href="{{ route('submission-tasks.manage.index', $course) }}" 
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
</script>
