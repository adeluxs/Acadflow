<?php
    $chartRows = collect($rows);
    $days = $chartRows->pluck('day')->unique()->sort()->values();
    $series = $chartRows->groupBy('series');
    $maximum = max(1, (float) $chartRows->max('value'));
    $colors = ['#4f46e5', '#059669', '#dc2626', '#d97706', '#0284c7'];
?>
<figure data-notification-chart class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5">
    <figcaption class="font-bold"><?php echo e($title); ?></figcaption>
    <?php if($chartRows->isEmpty()): ?><p class="mt-6 text-sm text-slate-500">No recorded events for this selection.</p><?php else: ?>
    <div class="mt-3 flex flex-wrap gap-3"><?php $__currentLoopData = $series; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $values): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><button type="button" data-chart-toggle="<?php echo e($loop->index); ?>" aria-pressed="true" class="rounded-lg border px-2 py-1 text-xs" style="color:<?php echo e($colors[$loop->index % count($colors)]); ?>"><?php echo e($label); ?></button><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
    <svg viewBox="0 0 600 180" role="img" aria-label="<?php echo e($title); ?>" class="mt-4 w-full"><path d="M28 10 V150 H585" fill="none" stroke="#cbd5e1"/>
        <?php $__currentLoopData = $series; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $values): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
            $byDay = $values->keyBy('day');
            $points = $days->map(fn ($day, $i) => (28 + ($i * 552 / max(1, $days->count() - 1))).','. (150 - ((float) ($byDay[$day]['value'] ?? 0) / $maximum * 130)))->implode(' ');
        ?>
        <g data-chart-line="<?php echo e($loop->index); ?>"><polyline fill="none" stroke="<?php echo e($colors[$loop->index % count($colors)]); ?>" stroke-width="2.5" points="<?php echo e($points); ?>"/>
        <?php $__currentLoopData = $days; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><circle cx="<?php echo e(28 + ($loop->index * 552 / max(1, $days->count() - 1))); ?>" cy="<?php echo e(150 - ((float) ($byDay[$day]['value'] ?? 0) / $maximum * 130)); ?>" r="3" fill="currentColor"><title><?php echo e($day); ?> · <?php echo e($label); ?>: <?php echo e($byDay[$day]['value'] ?? 0); ?></title></circle><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></g>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <text x="28" y="173" font-size="10" fill="#64748b"><?php echo e($days->first()); ?></text><text x="500" y="173" font-size="10" fill="#64748b"><?php echo e($days->last()); ?></text>
    </svg>
    <details class="mt-2 text-xs"><summary class="cursor-pointer font-semibold text-indigo-600">View recorded data</summary><div class="mt-3 max-h-56 overflow-auto"><table class="w-full text-left"><thead><tr><th>Date</th><th>Series</th><th>Count</th></tr></thead><tbody><?php $__currentLoopData = $chartRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td class="py-1"><?php echo e($row['day']); ?></td><td><?php echo e($row['series']); ?></td><td><?php echo e($row['value']); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div></details>
    <?php endif; ?>
</figure>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/admin/notifications/_trend.blade.php ENDPATH**/ ?>