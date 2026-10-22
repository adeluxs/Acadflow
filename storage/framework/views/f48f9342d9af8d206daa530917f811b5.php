<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Final Year Project - <?php echo e($submission->title); ?></title>
    <style>
        body { font-family: Times New Roman, serif; margin: 2cm; line-height: 1.5; }
        .header { text-align: center; margin-bottom: 2cm; }
        .header img { height: 80px; }
        .header h1 { font-size: 18pt; margin: 10px 0; }
        .header p { font-size: 12pt; margin: 5px 0; }
        .title { text-align: center; font-size: 16pt; font-weight: bold; margin: 2cm 0; text-transform: uppercase; }
        .meta { margin: 1cm 0; }
        .meta p { margin: 5px 0; }
        .content { margin-top: 1cm; }
        .footer { margin-top: 2cm; text-align: center; font-size: 10pt; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">
        <img src="<?php echo e(public_path('images/university-logo.png')); ?>" alt="University Logo">
        <h1><?php echo e($submission->course->department->faculty->university->name ?? 'University Name'); ?></h1>
        <p>Department of <?php echo e($submission->course->department->name); ?></p>
        <p>Final Year Project Report</p>
    </div>

    <div class="title"><?php echo e($submission->title); ?></div>

    <div class="meta">
        <p><strong>Student:</strong> <?php echo e($submission->user->first_name); ?> <?php echo e($submission->user->last_name); ?></p>
        <p><strong>Matric Number:</strong> <?php echo e($submission->user->student_id ?? 'N/A'); ?></p>
        <p><strong>Course:</strong> <?php echo e($submission->course->name); ?> (<?php echo e($submission->course->code); ?>)</p>
        <p><strong>Submission Date:</strong> <?php echo e($submission->submitted_at?->format('F d, Y')); ?></p>
        <?php if($submission->grade): ?>
            <p><strong>Grade:</strong> <?php echo e($submission->grade->score); ?>/<?php echo e($submission->grade->max_score); ?></p>
        <?php endif; ?>
    </div>

    <div class="content">
        <h2>Abstract</h2>
        <p><?php echo e($submission->description ?? 'No description provided.'); ?></p>

        <div class="page-break"></div>

        <h2>Main Content</h2>
        <?php $__currentLoopData = $submission->versions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $version): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <p>File: <?php echo e($version->file_name); ?> (<?php echo e(round($version->file_size / 1024, 2)); ?> KB)</p>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <div class="footer">
        <p>Generated on <?php echo e($generated_at->format('F d, Y H:i:s')); ?></p>
        <p><?php echo e(\App\Services\SettingService::get('site_name', 'AcadSyntra')); ?> - Academic research, publishing and collaboration platform</p>
    </div>
</body>
</html>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/pdfs/project.blade.php ENDPATH**/ ?>