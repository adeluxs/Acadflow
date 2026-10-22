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

                <?php if (isset($component)) { $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-lookup','data' => ['class' => 'mb-4','name' => 'department_id','kind' => 'departments','label' => 'Department','selected' => $selectedDepartment,'required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-lookup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mb-4','name' => 'department_id','kind' => 'departments','label' => 'Department','selected' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedDepartment),'required' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f)): ?>
<?php $attributes = $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f; ?>
<?php unset($__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f)): ?>
<?php $component = $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f; ?>
<?php unset($__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-lookup','data' => ['class' => 'mb-6','name' => 'semester_id','kind' => 'semesters','label' => 'Semester (optional, for auto-enrollment)','placeholder' => 'No auto-enrollment','selected' => $selectedSemester,'depends' => 'department_id']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-lookup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mb-6','name' => 'semester_id','kind' => 'semesters','label' => 'Semester (optional, for auto-enrollment)','placeholder' => 'No auto-enrollment','selected' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedSemester),'depends' => 'department_id']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f)): ?>
<?php $attributes = $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f; ?>
<?php unset($__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f)): ?>
<?php $component = $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f; ?>
<?php unset($__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f); ?>
<?php endif; ?>

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