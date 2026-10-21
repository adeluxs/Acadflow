<?php $__env->startSection('title', 'Import Students'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold mb-6">Import Students</h1>

        <?php if(session('import_errors')): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                <p class="font-bold">Import Errors:</p>
                <ul class="list-disc list-inside text-sm">
                    <?php $__currentLoopData = session('import_errors'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="mb-6">
                <h2 class="text-lg font-semibold mb-2">CSV Format Requirements</h2>
                <p class="text-sm text-gray-600 mb-2">Your CSV file must contain the following columns:</p>
                <code class="block bg-gray-100 p-3 rounded text-sm">
                    first_name, last_name, email, student_id, phone, password
                </code>
                <p class="text-sm text-gray-600 mt-2">
                    The <code class="bg-gray-100 px-1 rounded">password</code> column is optional. Default is <code class="bg-gray-100 px-1 rounded">password123</code>.
                </p>
            </div>

            <form method="POST" action="<?php echo e(route('admin.students.import.post')); ?>" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>

                <div class="mb-4">
                    <label for="csv_file" class="block text-sm font-medium text-gray-700 mb-2">Select CSV File</label>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>

                <div class="mb-4">
                    <label for="department_id" class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                    <select name="department_id" id="department_id" required
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select Department</option>
                        <?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $department): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($department->id); ?>"><?php echo e($department->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="mb-6">
                    <label for="semester_id" class="block text-sm font-medium text-gray-700 mb-2">Semester (Optional - for auto-enrollment)</label>
                    <select name="semester_id" id="semester_id"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select Semester</option>
                        <?php $__currentLoopData = $semesters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $semester): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($semester->id); ?>"><?php echo e($semester->name); ?> - <?php echo e($semester->academicSession->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="flex items-center justify-between">
                    <a href="<?php echo e(route('admin.dashboard')); ?>" class="text-gray-600 hover:text-gray-900">Cancel</a>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Import Students
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/students/import.blade.php ENDPATH**/ ?>