<?php
    $value = $setting->value;
    $key = $setting->key;
    $type = $setting->type;
    $displayValue = is_array($value) || is_object($value)
        ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        : (string) ($value ?? '');
?>

<?php if($type === 'boolean'): ?>
    <select name="settings[<?php echo e($key); ?>]" class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        <option value="1" <?php echo e($value == '1' || $value === true ? 'selected' : ''); ?>>Enabled</option>
        <option value="0" <?php echo e($value == '0' || $value === false ? 'selected' : ''); ?>>Disabled</option>
    </select>
<?php elseif($type === 'integer'): ?>
    <input type="number" name="settings[<?php echo e($key); ?>]" 
           value="<?php echo e($displayValue); ?>"
           class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
<?php elseif($type === 'json'): ?>
    <textarea name="settings[<?php echo e($key); ?>]" 
              rows="3"
              class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              placeholder="Enter valid JSON"><?php echo e($displayValue); ?></textarea>
<?php elseif($key === 'current_semester'): ?>
    <select name="settings[<?php echo e($key); ?>]" class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        <option value="first" <?php echo e($value === 'first' ? 'selected' : ''); ?>>First Semester</option>
        <option value="second" <?php echo e($value === 'second' ? 'selected' : ''); ?>>Second Semester</option>
        <option value="summer" <?php echo e($value === 'summer' ? 'selected' : ''); ?>>Summer</option>
    </select>
<?php elseif($key === 'digest_frequency'): ?>
    <select name="settings[<?php echo e($key); ?>]" class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        <option value="realtime" <?php echo e($value === 'realtime' ? 'selected' : ''); ?>>Real-time</option>
        <option value="daily" <?php echo e($value === 'daily' ? 'selected' : ''); ?>>Daily Digest</option>
        <option value="weekly" <?php echo e($value === 'weekly' ? 'selected' : ''); ?>>Weekly Digest</option>
    </select>
<?php elseif($key === 'default_grading_scale'): ?>
    <select name="settings[<?php echo e($key); ?>]" class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        <option value="100" <?php echo e($value === '100' ? 'selected' : ''); ?>>100-Point Scale</option>
        <option value="4.0" <?php echo e($value === '4.0' ? 'selected' : ''); ?>>4.0 GPA Scale</option>
        <option value="10" <?php echo e($value === '10' ? 'selected' : ''); ?>>10-Point Scale</option>
    </select>
<?php elseif($key === 'timezone'): ?>
    <select name="settings[<?php echo e($key); ?>]" class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        <option value="">Select Timezone</option>
        <?php $__currentLoopData = ['Africa/Lagos','Africa/Accra','Africa/Cairo','Africa/Johannesburg','Africa/Nairobi','America/New_York','America/Los_Angeles','America/Chicago','Europe/London','Europe/Paris','Europe/Berlin','Asia/Tokyo','Asia/Shanghai','Asia/Kolkata','Asia/Dubai','Australia/Sydney','Pacific/Auckland']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tz): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($tz); ?>" <?php echo e($value === $tz ? 'selected' : ''); ?>><?php echo e($tz); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
<?php elseif($key === 'default_language'): ?>
    <select name="settings[<?php echo e($key); ?>]" class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        <?php $__currentLoopData = ['en'=>'English','fr'=>'French','es'=>'Spanish','de'=>'German','pt'=>'Portuguese','ar'=>'Arabic','zh'=>'Chinese','ja'=>'Japanese','yo'=>'Yoruba','ha'=>'Hausa']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lang => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($lang); ?>" <?php echo e($value === $lang ? 'selected' : ''); ?>><?php echo e($label); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
<?php elseif($key === 'site_logo' || $key === 'site_favicon'): ?>
    <input type="text" name="settings[<?php echo e($key); ?>]" 
           value="<?php echo e($displayValue); ?>"
           placeholder="Enter URL or path to <?php echo e($key === 'site_logo' ? 'logo' : 'favicon'); ?>"
           class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
    <p class="text-xs text-gray-500 mt-1">Enter a URL or relative path (e.g., /images/logo.png)</p>
<?php elseif(str_contains($key, 'date') || str_contains($key, 'time')): ?>
    <input type="date" name="settings[<?php echo e($key); ?>]" 
           value="<?php echo e($displayValue); ?>"
           class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
<?php elseif(str_contains($key, 'email')): ?>
    <input type="email" name="settings[<?php echo e($key); ?>]" 
           value="<?php echo e($displayValue); ?>"
           class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
<?php else: ?>
    <input type="text" name="settings[<?php echo e($key); ?>]" 
           value="<?php echo e($displayValue); ?>"
           class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
<?php endif; ?>
<?php /**PATH C:\Users\Admin\Desktop\Acadflow\resources\views/settings/partials/field.blade.php ENDPATH**/ ?>