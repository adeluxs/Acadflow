<template>
    <div class="file-upload">
        <!-- Drop Zone -->
        <div 
            class="drop-zone"
            :class="{ 'drag-over': isDragging, 'has-files': files.length > 0 }"
            @dragover.prevent="handleDragOver"
            @dragleave="handleDragLeave"
            @drop.prevent="handleDrop"
            @click="triggerFileInput"
        >
            <input 
                ref="fileInput"
                type="file"
                :multiple="multiple"
                :accept="acceptedTypes"
                class="hidden"
                @change="handleFileSelect"
            >
            
            <div v-if="files.length === 0" class="text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                <p class="mt-2 text-sm text-gray-600">
                    <span class="font-medium text-blue-600">Click to upload</span> or drag and drop
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ allowedExtensions }} up to {{ maxSizeMB }}MB
                </p>
            </div>
        </div>

        <!-- File List -->
        <div v-if="files.length > 0" class="mt-4 space-y-2">
            <div 
                v-for="(file, index) in files" 
                :key="index"
                class="file-item flex items-center justify-between p-3 bg-gray-50 rounded-lg"
            >
                <div class="flex items-center gap-3">
                    <!-- File Icon -->
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    
                    <!-- File Info -->
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ file.name }}</p>
                        <p class="text-xs text-gray-500">{{ formatFileSize(file.size) }}</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <!-- Progress Bar -->
                    <div v-if="file.uploading" class="w-24">
                        <div class="bg-gray-200 rounded-full h-2">
                            <div 
                                class="bg-blue-500 h-2 rounded-full transition-all"
                                :style="{ width: file.progress + '%' }"
                            ></div>
                        </div>
                    </div>
                    
                    <!-- Status Icon -->
                    <div v-if="file.uploaded" class="text-green-500">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    
                    <!-- Error Icon -->
                    <div v-if="file.error" class="text-red-500">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    
                    <!-- Remove Button -->
                    <button 
                        v-if="!file.uploading"
                        @click="removeFile(index)"
                        class="text-gray-400 hover:text-red-500"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Upload Button -->
        <div v-if="files.length > 0 && hasPendingFiles" class="mt-4 flex justify-end">
            <button 
                @click="uploadAll"
                :disabled="uploading"
                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50"
            >
                {{ uploading ? 'Uploading...' : 'Upload All' }}
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'FileUpload',
    props: {
        multiple: {
            type: Boolean,
            default: true
        },
        maxFiles: {
            type: Number,
            default: 10
        },
        maxSizeMB: {
            type: Number,
            default: 50
        },
        acceptedTypes: {
            type: String,
            default: '.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.rar,.jpg,.png,.mp4,.mp3'
        },
        uploadEndpoint: {
            type: String,
            default: '/api/v1/submissions/upload'
        },
        existingFiles: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            files: [],
            isDragging: false,
            uploading: false
        };
    },
    computed: {
        allowedExtensions() {
            return this.acceptedTypes.split(',').map(ext => ext.trim().replace('.', '').toUpperCase()).join(', ');
        },
        hasPendingFiles() {
            return this.files.some(f => !f.uploaded && !f.error);
        }
    },
    mounted() {
        // Add existing files
        this.files = this.existingFiles.map(f => ({
            ...f,
            uploaded: true
        }));
    },
    methods: {
        triggerFileInput() {
            this.$refs.fileInput.click();
        },
        handleDragOver(e) {
            this.isDragging = true;
        },
        handleDragLeave() {
            this.isDragging = false;
        },
        handleDrop(e) {
            this.isDragging = false;
            const droppedFiles = Array.from(e.dataTransfer.files);
            this.addFiles(droppedFiles);
        },
        handleFileSelect(e) {
            const selectedFiles = Array.from(e.target.files);
            this.addFiles(selectedFiles);
            e.target.value = ''; // Reset input
        },
        addFiles(newFiles) {
            for (const file of newFiles) {
                if (this.files.length >= this.maxFiles) {
                    this.$emit('error', `Maximum ${this.maxFiles} files allowed`);
                    break;
                }
                
                // Validate size
                if (file.size > this.maxSizeMB * 1024 * 1024) {
                    this.$emit('error', `${file.name} exceeds ${this.maxSizeMB}MB limit`);
                    continue;
                }
                
                // Check for duplicates
                if (this.files.some(f => f.name === file.name && f.size === file.size)) {
                    continue;
                }
                
                this.files.push({
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    file: file,
                    uploading: false,
                    uploaded: false,
                    progress: 0,
                    error: null
                });
            }
        },
        removeFile(index) {
            this.files.splice(index, 1);
        },
        async uploadAll() {
            this.uploading = true;
            
            for (let i = 0; i < this.files.length; i++) {
                const file = this.files[i];
                if (file.uploaded || file.uploading) continue;
                
                file.uploading = true;
                
                try {
                    const formData = new FormData();
                    formData.append('file', file.file);
                    
                    const response = await axios.post(this.uploadEndpoint, formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        },
                        onUploadProgress: (progressEvent) => {
                            file.progress = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                        }
                    });
                    
                    file.uploaded = true;
                    file.file_url = response.data.url;
                    this.$emit('uploaded', response.data);
                } catch (error) {
                    file.error = error.response?.data?.message || 'Upload failed';
                    this.$emit('error', file.error);
                } finally {
                    file.uploading = false;
                }
            }
            
            this.uploading = false;
        },
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    }
};
</script>

<style scoped>
.drop-zone {
    border: 2px dashed #d1d5db;
    border-radius: 0.5rem;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}

.drop-zone:hover {
    border-color: #3b82f6;
    background-color: #f9fafb;
}

.drop-zone.drag-over {
    border-color: #3b82f6;
    background-color: #eff6ff;
}

.file-item {
    transition: background-color 0.2s;
}

.file-item:hover {
    background-color: #f3f4f6;
}
</style>