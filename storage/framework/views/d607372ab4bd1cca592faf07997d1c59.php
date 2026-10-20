<?php
    $siteName = (string) \App\Services\SettingService::getGlobal('site_name', 'AcadSyntra');
    $supportEmail = \App\Services\PublicWebsite::supportEmail();
    $primaryColor = (string) \App\Services\SettingService::getGlobal('primary_color', '#4f46e5');
    if (!preg_match('/^#[0-9a-f]{6}$/i', $primaryColor)) $primaryColor = '#4f46e5';
    $canonical = ($slug ?? 'home') === 'home' ? route('home') : (isset(\App\Services\PublicWebsite::PATHS[$slug]) ? route('public.'.$slug) : route('public.page', ['page' => $slug]));
    $description = $page['description'] ?? $page['intro'];
    $nav = ['home' => ['Home', route('home')], 'features' => ['Features', route('public.features')], 'how-it-works' => ['How it works', route('public.how-it-works')], 'about' => ['About', route('public.about')], 'contact' => ['Contact', route('public.contact')], 'faq' => ['FAQs', route('public.faq')]];
    $schema = [
        '@context' => 'https://schema.org', '@graph' => [
            ['@type' => 'WebSite', '@id' => route('home').'#website', 'url' => route('home'), 'name' => $siteName],
            ['@type' => 'WebPage', 'url' => $canonical, 'name' => $page['title'], 'description' => $description, 'isPartOf' => ['@id' => route('home').'#website']],
        ],
    ];
    if (($slug ?? '') === 'home') $schema['@graph'][] = ['@type' => 'SoftwareApplication', 'name' => $siteName, 'applicationCategory' => 'EducationalApplication', 'operatingSystem' => 'Web', 'url' => route('home'), 'description' => $description];
    if (in_array($slug ?? '', ['faq', 'help'], true)) $schema['@graph'][] = ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($faq) => ['@type' => 'Question', 'name' => $faq['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']]], $faqs)];
    $configuredLogo = trim((string) \App\Services\SettingService::getGlobal('site_logo', ''));
    $logo = (str_starts_with($configuredLogo, '/') && !str_starts_with($configuredLogo, '//')) || filter_var($configuredLogo, FILTER_VALIDATE_URL) && str_starts_with($configuredLogo, 'https://') ? $configuredLogo : asset('branding/acadsyntra-mark.png');
?>
<!doctype html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?php echo e($primaryColor); ?>">
    <meta name="description" content="<?php echo e($description); ?>">
    <title><?php echo e($page['title']); ?> · <?php echo e($siteName); ?></title>
    <link rel="canonical" href="<?php echo e($canonical); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo e($siteName); ?>">
    <meta property="og:title" content="<?php echo e($page['title']); ?>">
    <meta property="og:description" content="<?php echo e($description); ?>">
    <meta property="og:url" content="<?php echo e($canonical); ?>">
    <meta property="og:image" content="<?php echo e(asset('branding/acadsyntra-social.png')); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?php echo e($siteName); ?> logo">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo e($page['title']); ?>">
    <meta name="twitter:description" content="<?php echo e($description); ?>">
    <meta name="twitter:image" content="<?php echo e(asset('branding/acadsyntra-social.png')); ?>">
    <link rel="icon" href="<?php echo e(asset('branding/acadsyntra-icon-32.png')); ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo e(asset('branding/acadsyntra-icon-180.png')); ?>">
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/public.css', 'resources/js/public-website.js']); ?>
    <style>:root { --brand: <?php echo e($primaryColor); ?>; }</style>
    <script type="application/ld+json"><?php echo json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?></script>
</head>
<body class="public-site">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header">
    <div class="site-container header-inner">
        <a class="site-brand" href="<?php echo e(route('home')); ?>" aria-label="<?php echo e($siteName); ?> home"><img src="<?php echo e($logo); ?>" alt="" width="37" height="37"><span><?php echo e($siteName); ?></span><span class="brand-dot" aria-hidden="true"></span></a>
        <nav class="desktop-nav" aria-label="Main navigation">
            <?php $__currentLoopData = $nav; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$label, $url]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e($url); ?>" <?php if(($slug ?? '') === $key): ?> aria-current="page" <?php endif; ?>><?php echo e($label); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </nav>
        <div class="header-actions"><?php if(auth()->guard()->check()): ?><a class="button button-small" href="<?php echo e(route('dashboard')); ?>">Your dashboard <span aria-hidden="true">↗</span></a><?php else: ?><a class="login-link" href="<?php echo e(route('login')); ?>">Log in</a><a class="button button-small" href="<?php echo e(route('register')); ?>">Get started <span aria-hidden="true">↗</span></a><?php endif; ?></div>
        <button class="menu-trigger" type="button" data-menu-open aria-controls="mobile-navigation" aria-expanded="false" aria-label="Open navigation"><?php echo $__env->make('public.partials.icon', ['icon' => 'menu'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></button>
        <noscript><a class="no-js-nav" href="#footer-navigation">Menu</a></noscript>
    </div>
</header>
<dialog id="mobile-navigation" class="mobile-navigation" aria-label="Mobile navigation">
    <div class="mobile-menu-top"><span class="site-brand"><?php echo e($siteName); ?><span class="brand-dot"></span></span><button type="button" data-menu-close class="icon-button" aria-label="Close navigation"><?php echo $__env->make('public.partials.icon', ['icon' => 'close'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></button></div>
    <nav aria-label="Mobile main navigation"><?php $__currentLoopData = $nav; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$label, $url]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e($url); ?>" <?php if(($slug ?? '') === $key): ?> aria-current="page" <?php endif; ?>><?php echo e($label); ?> <span aria-hidden="true">↗</span></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></nav>
    <div class="mobile-menu-actions"><?php if(auth()->guard()->check()): ?><a class="button" href="<?php echo e(route('dashboard')); ?>">Open dashboard</a><?php else: ?><a class="button" href="<?php echo e(route('register')); ?>">Get started</a><a class="button button-secondary" href="<?php echo e(route('login')); ?>">Log in</a><?php endif; ?></div>
</dialog>
<main id="main-content" tabindex="-1"><?php echo $__env->yieldContent('content'); ?></main>
<footer class="site-footer" id="footer-navigation">
    <div class="site-container footer-grid">
        <div class="footer-intro"><a class="site-brand" href="<?php echo e(route('home')); ?>"><img src="<?php echo e($logo); ?>" width="36" height="36" alt=""><span><?php echo e($siteName); ?></span><span class="brand-dot"></span></a><p>A clearer path through learning,<br>research and knowledge sharing.</p><a class="text-link" href="<?php echo e(route('public.contact')); ?>">Talk to the team <span aria-hidden="true">↗</span></a></div>
        <nav aria-label="Product links"><h2>Product</h2><a href="<?php echo e(route('public.features')); ?>">Features</a><a href="<?php echo e(route('public.how-it-works')); ?>">How it works</a><a href="<?php echo e(route('register')); ?>">Getting started</a><a href="<?php echo e(route('public.page', ['page' => 'documentation'])); ?>">Product guide</a></nav>
        <nav aria-label="Company links"><h2>Company</h2><a href="<?php echo e(route('public.about')); ?>">About us</a><a href="<?php echo e(route('public.contact')); ?>">Contact us</a><a href="<?php echo e(route('public.faq')); ?>">FAQs</a></nav>
        <nav aria-label="Support links"><h2>Support</h2><a href="<?php echo e(route('public.help')); ?>">Help center</a><a href="<?php echo e(route('public.contact')); ?>">Support contact</a><a href="<?php echo e(route('public.page', ['page' => 'security'])); ?>">Security</a><?php if($supportEmail): ?><a href="mailto:<?php echo e($supportEmail); ?>"><?php echo e($supportEmail); ?></a><?php endif; ?></nav>
        <nav aria-label="Legal links"><h2>Legal</h2><a href="<?php echo e(route('public.privacy')); ?>">Privacy policy</a><a href="<?php echo e(route('public.terms')); ?>">Terms of service</a><a href="<?php echo e(route('public.cookies')); ?>">Cookie policy</a><a href="<?php echo e(route('public.disclaimer')); ?>">Disclaimer</a></nav>
    </div>
    <div class="site-container footer-bottom"><p>© <?php echo e(date('Y')); ?> <?php echo e($siteName); ?>.</p><span>Make room for your next idea.</span><a href="#main-content">Back to top ↑</a></div>
</footer>
</body>
</html>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/layouts/public.blade.php ENDPATH**/ ?>