<?php $__env->startSection('title','Attendance Session'); ?>
<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <section class="rounded-[2rem] bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-900 p-7 text-white shadow-xl sm:p-9">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div><a href="<?php echo e(auth()->user()->isLecturer() ? route('attendance.lecturer') : route('admin.reports')); ?>" class="text-sm font-semibold text-emerald-200">← Back</a><p class="mt-5 text-xs font-black uppercase tracking-[.2em] text-emerald-300"><?php echo e($session->course->code); ?> · Live attendance</p><h1 class="mt-2 text-3xl font-black sm:text-4xl"><?php echo e($session->course->name); ?></h1><p class="mt-2 text-sm text-slate-300">Lecturer: <?php echo e($session->lecturer->full_name); ?></p></div>
            <div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-4 py-2 text-sm font-black <?php echo e($session->status==='active'?'bg-emerald-400/20 text-emerald-200':'bg-white/10 text-slate-200'); ?>"><?php echo e(str($session->status)->headline()); ?></span><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('stop',$session)): ?><form method="POST" action="<?php echo e(route('attendance.close',$session)); ?>" onsubmit="return confirm('Close this attendance session? Pending students will be marked absent.')"><?php echo csrf_field(); ?><button class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-black text-white">Close session</button></form><?php endif; ?></div>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">Started</p><p class="mt-2 font-black text-slate-900"><?php echo e($session->started_at->format('M j · H:i')); ?></p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">Ended</p><p class="mt-2 font-black text-slate-900"><?php echo e($session->ended_at?->format('M j · H:i') ?? 'Ongoing'); ?></p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">Students</p><p class="mt-2 text-2xl font-black text-slate-900"><?php echo e($summary['total']); ?></p></div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"><p class="text-xs font-bold uppercase text-emerald-600">Present / late</p><p class="mt-2 text-2xl font-black text-emerald-800"><?php echo e($summary['present']); ?> / <?php echo e($summary['late']); ?></p></div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5"><p class="text-xs font-bold uppercase text-indigo-600">Attendance rate</p><p class="mt-2 text-2xl font-black text-indigo-800"><?php echo e(number_format($summary['present_rate'],1)); ?>%</p></div>
    </div>

    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('edit',$session)): ?>
        <section class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm"><div class="flex flex-col gap-6 md:flex-row md:items-center"><canvas id="qr-code" class="rounded-2xl bg-white p-3 shadow-sm" aria-label="Attendance check-in QR code"></canvas><div><p class="text-xs font-black uppercase tracking-[.18em] text-emerald-600">Student check-in QR</p><h2 class="mt-1 text-xl font-black text-slate-900">Scan to check in</h2><p class="mt-2 text-sm text-slate-600">Expires at <span id="expires-at" class="font-bold"><?php echo e($session->qr_expires_at->format('H:i:s')); ?></span>. Students must be signed in and enrolled.</p><button type="button" onclick="refreshQr()" class="mt-4 rounded-xl bg-emerald-700 px-4 py-2 text-sm font-black text-white">Refresh QR code</button><p id="qr-error" class="mt-2 hidden text-sm font-semibold text-rose-700"></p><button id="qr-retry" type="button" onclick="refreshQr()" class="mt-2 hidden rounded-xl border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-700">Try Again</button></div></div></section>
    <?php endif; ?>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5"><div><p class="text-xs font-black uppercase tracking-[.18em] text-emerald-600">Session register</p><h2 class="mt-1 text-xl font-black text-slate-900">Attendance records</h2></div><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('export',$session)): ?><a href="<?php echo e(route('admin.reports.export',['type'=>'attendance','session_id'=>$session->id])); ?>" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700">Export report</a><?php endif; ?></div>
        <div class="overflow-x-auto"><table class="min-w-full"><thead class="bg-slate-50 text-left text-[11px] font-black uppercase tracking-wider text-slate-400"><tr><th class="px-6 py-3">Student</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Check-in</th><th class="px-6 py-3">Location</th><th class="px-6 py-3">Verification</th></tr></thead><tbody class="divide-y divide-slate-100"><?php $__empty_1 = true; $__currentLoopData = $session->records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $record): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr class="hover:bg-slate-50"><td class="px-6 py-4"><p class="font-black text-slate-900"><?php echo e($record->user->full_name); ?></p><p class="text-xs text-slate-400"><?php echo e($record->user->email); ?></p></td><td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold <?php echo e($record->status==='present'?'bg-emerald-100 text-emerald-700':($record->status==='late'?'bg-amber-100 text-amber-700':(($record->status==='absent'||$record->status==='invalid')?'bg-rose-100 text-rose-700':'bg-slate-100 text-slate-600'))); ?>"><?php echo e(str($record->status)->headline()); ?></span></td><td class="px-6 py-4 text-sm font-semibold text-slate-600"><?php echo e($record->check_in_at?->format('M j · H:i') ?? 'Not checked in'); ?></td><td class="px-6 py-4 text-xs text-slate-500"><?php if($record->latitude!==null&&$record->longitude!==null): ?><?php echo e(number_format((float)$record->latitude,6)); ?>, <?php echo e(number_format((float)$record->longitude,6)); ?><?php else: ?>—<?php endif; ?></td><td class="px-6 py-4 text-sm text-slate-500"><?php echo e($record->verification_notes ?: ($record->status==='pending'?'Pending':'Not recorded')); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">No enrolled students were found for this session.</td></tr><?php endif; ?></tbody></table></div>
    </section>
</div>
<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('edit', $session)): ?>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
const initialQrPayload = <?php echo json_encode($qrPayload, 15, 512) ?>;
const refreshQrUrl = <?php echo json_encode(route('attendance.qr.refresh', $session), 512) ?>;
const csrfToken = <?php echo json_encode(csrf_token(), 15, 512) ?>;
let refreshInterval;

function generateQrCode(data) {
    const canvas = document.getElementById('qr-code');
    QRCode.toCanvas(canvas, data, { width: 180, margin: 1 }, function (error) {
        if (error) {
            document.getElementById('qr-error').textContent = 'Unable to render the QR code.';
            document.getElementById('qr-error').classList.remove('hidden');
        }
    });
}

async function refreshQr() {
    const errorElement = document.getElementById('qr-error');
    const retryButton = document.getElementById('qr-retry');
    errorElement.classList.add('hidden');
    retryButton?.classList.add('hidden');

    try {
        const response = await fetch(refreshQrUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
        });
        const data = await response.json();

        if (!response.ok || data.success === false) {
            const requestError = new Error(data.message || 'Unable to refresh the QR code.');
            requestError.data = data;
            requestError.status = response.status;
            throw requestError;
        }

        generateQrCode(data.qr_payload);
        document.getElementById('expires-at').textContent = new Date(data.qr_expires_at).toLocaleTimeString();
    } catch (error) {
        const detail = window.AcadFlowFeedback?.normalize(error, 'Unable to refresh the QR code right now.') || {
            message: 'Unable to refresh the QR code right now.', retryable: true
        };
        errorElement.textContent = detail.message;
        errorElement.classList.remove('hidden');
        if (detail.retryable) retryButton?.classList.remove('hidden');
    }
}

generateQrCode(initialQrPayload);
refreshInterval = window.setInterval(refreshQr, 50000);
window.addEventListener('beforeunload', () => window.clearInterval(refreshInterval));
</script>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/attendance/session.blade.php ENDPATH**/ ?>