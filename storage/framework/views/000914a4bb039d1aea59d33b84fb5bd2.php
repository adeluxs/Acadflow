<?php
$paths = [
 'book' => 'M4 4h5a3 3 0 0 1 3 3v14a4 4 0 0 0-4-3H4V4Zm16 0h-5a3 3 0 0 0-3 3v14a4 4 0 0 1 4-3h4V4Z',
 'research' => 'M9 3h6m-5 0v6L5 18a2 2 0 0 0 2 3h10a2 2 0 0 0 2-3l-5-9V3M8 14h8',
 'layers' => 'm12 3 10 5-10 5L2 8l10-5Zm-9 10 9 5 9-5M3 18l9 5 9-5',
 'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m13-17a4 4 0 0 1 0 8m7 9v-2a4 4 0 0 0-3-4M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
 'spark' => 'm12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3ZM20 2v4m-2-2h4',
 'building' => 'M3 21h18M5 21V5l7-3 7 3v16M9 7h1m4 0h1M9 11h1m4 0h1M9 15h1m4 0h1m-4 6v-3h2v3',
 'arrow' => 'M5 12h14m-6-6 6 6-6 6', 'check' => 'm5 12 4 4L19 6',
 'menu' => 'M4 6h16M4 12h16M4 18h16', 'close' => 'm6 6 12 12M6 18 18 6',
 'search' => 'm21 21-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z',
 'shield' => 'm12 2 9 4v6c0 6-9 10-9 10S3 18 3 12V6l9-4Zm-4 10 3 3 5-6',
 'chat' => 'M21 11a8 8 0 0 1-8 8H7l-5 3V11a9 9 0 0 1 19 0ZM7 10h10M7 14h6',
 'clock' => 'M12 7v5l3 2M22 12A10 10 0 1 1 2 12a10 10 0 0 1 20 0Z',
 'folder' => 'M3 7V4h6l3 3h9v13H3V7Z',
];
?>
<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?php echo e($paths[$icon] ?? $paths['layers']); ?>"/></svg>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/public/partials/icon.blade.php ENDPATH**/ ?>