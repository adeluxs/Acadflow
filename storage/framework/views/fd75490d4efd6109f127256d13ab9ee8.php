<?php
    $profileLevel = strtolower(trim((string) auth()->user()->academic_level));
    $selectedResearchLevel = old('research_level', match (true) {
        array_key_exists($profileLevel, config('research_methodologies.levels')) => $profileLevel,
        (bool) preg_match('/ph\.?d|doctor/i', $profileLevel) => 'doctoral',
        (bool) preg_match('/master|m\.?sc|m\.?a\b|m\.?eng|mba/i', $profileLevel) => 'masters',
        (bool) preg_match('/^hnd|higher national/i', $profileLevel) => 'hnd',
        (bool) preg_match('/^nd|national diploma/i', $profileLevel) => 'nd',
        (bool) preg_match('/postgrad|pgd/i', $profileLevel) => 'postgraduate',
        (bool) preg_match('/professional|independent/i', $profileLevel) => 'professional',
        default => 'undergraduate',
    });
?>
<section class="studio-panel" data-setup-step="Design your study">
    <p class="studio-eyebrow">01 / Research design</p>
    <h2 class="studio-heading">A workspace shaped around your research.</h2>
    <p class="studio-muted">Choose a starting point. Your methodology determines the guidance, instruments and analysis plan; your institution determines submission requirements.</p>
    <div class="studio-grid mt-6">
        <label>Workspace<select name="studio_mode"><option value="research" <?php if(old('studio_mode', request('studio_mode')) !== 'final_year'): echo 'selected'; endif; ?>>Research Studio</option><option value="final_year" <?php if(old('studio_mode', request('studio_mode')) === 'final_year'): echo 'selected'; endif; ?>>Final-Year Project Studio</option></select></label>
        <label>Research level<select name="research_level"><?php $__currentLoopData = config('research_methodologies.levels'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($key); ?>" <?php if($selectedResearchLevel === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
        <label>Discipline<input name="discipline" maxlength="180" value="<?php echo e(old('discipline', auth()->user()->programme)); ?>" placeholder="e.g. Sociology, engineering, law"></label>
        <label>Institution <span class="font-normal text-slate-500">(optional)</span><input name="institution_name" maxlength="255" value="<?php echo e(old('institution_name', auth()->user()->university?->name)); ?>" placeholder="Independent research is welcome"></label>
        <label class="md:col-span-2">Methodology<select name="methodology" data-methodology-select><option value="">Use the administrator’s research-type template</option><?php $__currentLoopData = config('research_methodologies.methodologies'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($key); ?>" <?php if(old('methodology', 'quantitative') === $key): echo 'selected'; endif; ?>><?php echo e($method['label']); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
        <label>Document structure<select name="report_structure"><option value="methodology" <?php if(old('report_structure') === 'methodology'): echo 'selected'; endif; ?>>Adapt to methodology</option><option value="five_chapter" <?php if(old('report_structure', request('studio_mode') === 'final_year' ? 'five_chapter' : '') === 'five_chapter'): echo 'selected'; endif; ?>>Nigerian five-chapter format</option><option value="institutional" <?php if(old('report_structure') === 'institutional'): echo 'selected'; endif; ?>>Administrator’s research-type template</option></select></label>
        <label>Reference style<select name="citation_style"><?php $__currentLoopData = config('research_methodologies.styles'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($key); ?>" <?php if(old('citation_style', 'apa') === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
    </div>
    <div class="studio-callout mt-5" data-methodology-preview aria-live="polite"></div>
    <script type="application/json" data-methodology-catalogue><?php echo json_encode(config('research_methodologies.methodologies'), 15, 512) ?></script>
</section>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/research/partials/setup.blade.php ENDPATH**/ ?>