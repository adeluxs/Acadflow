@extends('layouts.app')

@section('title', 'Submit Assignment')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <!-- Breadcrumb -->
    <div class="mb-6 text-sm">
        @if(isset($task) && $task->course)
            <a href="{{ route('courses.show', $task->course) }}" class="text-blue-600 hover:underline">{{ $task->course->name }}</a>
            <span class="text-gray-600 mx-2">/</span>
            <a href="{{ route('courses.assignments', $task->course) }}" class="text-blue-600 hover:underline">Assignments</a>
            <span class="text-gray-600 mx-2">/</span>
            <a href="{{ route('submission-tasks.student.show', [$task->course, $task]) }}" class="text-blue-600 hover:underline">{{ $task->title }}</a>
        @else
            Assignments
        @endif
    </div>

    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Submit Assignment</h1>
        <p class="text-gray-600 mt-2">{{ $task->title }}</p>
    </div>

    @if($errors->any())
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <h3 class="font-bold text-red-900 mb-2">Please fix the following errors:</h3>
            <ul class="text-red-700 text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <li>• {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-3 gap-8">
        <!-- Left: Upload Form -->
        <div class="col-span-2">
    <form action="{{ route('submissions.store') }}" method="POST" enctype="multipart/form-data" id="submissionForm">
        @csrf
        @if(isset($task) && $task->course)
            <input type="hidden" name="course_id" value="{{ $task->course->id }}">
        @endif
        <input type="hidden" name="submission_task_id" value="{{ $task->id ?? '' }}">
        <input type="hidden" name="type" value="{{ $task->type ?? '' }}">
        <input type="hidden" name="title" value="{{ $task->title ?? '' }}">
        

                <!-- Files Upload -->
                <div class="bg-white rounded-lg shadow p-8 mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">📁 Upload Your Files</h2>

                    <!-- File Input Area -->
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center mb-6 cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition"
                         onclick="document.getElementById('fileInput').click()">
                        <input type="file" id="fileInput" name="files[]" multiple 
                                class="hidden" accept="{{ implode(',', $task->allowed_file_types ?? []) }}"
                               onchange="handleFileSelect(event)">
                        
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v28a4 4 0 004 4h24a4 4 0 004-4V20m-8-12v8h8m-8-8L20 4"></path>
                        </svg>
                        
                        <p class="text-xl font-semibold text-gray-900 mb-1">Drag files here or click to browse</p>
                        <p class="text-gray-600 text-sm">
                             Accepted formats: {{ strtoupper(implode(', ', $task->allowed_file_types ?? [])) }}
                        </p>
                        <p class="text-gray-500 text-sm mt-2">
                            Max {{ $task->max_file_count }} files, {{ $task->max_file_size_mb }}MB each
                        </p>
                    </div>

                    <!-- File Requirements Info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <p class="text-sm text-gray-700 mb-2">
                            <strong>Requirements:</strong>
                        </p>
                        <ul class="text-sm text-gray-700 space-y-1 ml-4">
                            <li>✓ Submit {{ $task->min_file_count }} to {{ $task->max_file_count }} files</li>
                            <li>✓ Each file max {{ $task->max_file_size_mb }}MB</li>
                             <li>✓ Allowed types: {{ implode(', ', $task->allowed_file_types ?? []) }}</li>
                        </ul>
                    </div>

                    <!-- Files List -->
                    <div id="filesList" class="space-y-2 mb-6">
                        <!-- Files will be added here dynamically -->
                    </div>

                    @error('files')
                        <p class="text-red-600 text-sm mb-4">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Comments -->
                <div class="bg-white rounded-lg shadow p-8 mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">💬 Submission Notes (Optional)</h2>

                    <textarea name="submission_notes" rows="6"
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Add any notes or comments for your instructor...">{{ old('submission_notes') }}</textarea>

                    <p class="text-gray-500 text-sm mt-2">
                        You can use this space to explain your work, mention any issues you encountered, or provide additional context.
                    </p>

                    @error('submission_notes')
                        <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Confirmation -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="confirm_submission" value="1" class="mt-1"
                               {{ old('confirm_submission') ? 'checked' : '' }}>
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
                    @error('confirm_submission')
                        <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Buttons -->
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-bold text-lg"
                            id="submitBtn" disabled>
                        📤 Submit Assignment
                    </button>
                    
                    <a href="{{ route('submission-tasks.student.show', [$task->course, $task]) }}" 
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
                <p class="text-lg font-bold text-gray-900">{{ $task->due_date->format('M d, Y') }}</p>
                <p class="text-sm text-gray-600">{{ $task->due_date->format('H:i') }}</p>
                
                @if(now() > $task->due_date && now() < $task->late_deadline)
                    <div class="mt-4 p-3 bg-orange-50 border border-orange-200 rounded">
                        <p class="text-orange-700 font-semibold text-sm">⚠ This is a late submission</p>
                        <p class="text-orange-600 text-xs mt-1">
                            {{ $task->late_submission_penalty_percent }}% penalty will be applied to your grade
                        </p>
                    </div>
                @endif

                @if(now() >= $task->late_deadline)
                    <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded">
                        <p class="text-red-700 font-semibold text-sm">❌ Hard deadline passed</p>
                        <p class="text-red-600 text-xs mt-1">
                            Submissions are no longer accepted
                        </p>
                    </div>
                @endif
            </div>

            <!-- Assignment Info -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="font-bold text-gray-900 mb-4">📋 Assignment Info</h3>
                <ul class="space-y-3 text-sm">
                    <li>
                        <p class="text-gray-600">Type</p>
                        <p class="font-semibold text-gray-900">{{ ucfirst($task->type) }} {{ ucfirst($task->title) }}</p>
                    </li>
                    <li>
                        <p class="text-gray-600">Points</p>
                        <p class="font-semibold text-gray-900">{{ $task->max_score }}</p>
                    </li>
                    <li>
                        <p class="text-gray-600">Resubmissions Left</p>
                        @php
                            $submission = $task->submissions->where('user_id', auth()->id())->first();
                            $resubmitsLeft = $task->max_resubmissions ? max(0, $task->max_resubmissions - ($submission?->resubmission_count ?? 0)) : '∞';
                        @endphp
                        <p class="font-semibold text-gray-900">{{ $resubmitsLeft }}</p>
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
    const maxSize = {{ $task->max_file_size_mb }} * 1024 * 1024;
     const allowedTypes = {!! json_encode($task->allowed_file_types ?? []) !!};
    const ext = file.name.split('.').pop().toLowerCase();
    
    return file.size <= maxSize && allowedTypes.includes(ext);
}

// Validate all files
function validateFiles() {
    const minFiles = {{ $task->min_file_count }};
    const maxFiles = {{ $task->max_file_count }};
    const allValid = selectedFiles.length >= minFiles && selectedFiles.length <= maxFiles && 
                    selectedFiles.every(f => validateFile(f));
    
    document.getElementById('submitBtn').disabled = !allValid || !document.querySelector('input[name="confirm_submission"]').checked;
}

// Enable submit on confirmation check
document.querySelector('input[name="confirm_submission"]').addEventListener('change', validateFiles);

// Drag and drop
const dropZone = document.querySelector('.border-dashed');
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    dropZone.classList.add('border-blue-400', 'bg-blue-50');
}

function unhighlight(e) {
    dropZone.classList.remove('border-blue-400', 'bg-blue-50');
}

        dropZone.addEventListener('drop', handleDrop, false);

        // Offline submission support
        const taskId = {{ $task->id }};
        const courseId = {{ $task->course->id }};
        
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
@endsection