@php
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
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $primaryColor }}">
    <meta name="description" content="{{ $description }}">
    <title>{{ $page['title'] }} · {{ $siteName }}</title>
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $page['title'] }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ asset('branding/acadsyntra-social.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $siteName }} logo">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $page['title'] }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ asset('branding/acadsyntra-social.png') }}">
    <link rel="icon" href="{{ asset('branding/acadsyntra-icon-32.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('branding/acadsyntra-icon-180.png') }}">
    @vite(['resources/css/public.css', 'resources/js/public-website.js'])
    <style>:root { --brand: {{ $primaryColor }}; }</style>
    <script type="application/ld+json">{!! json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
</head>
<body class="public-site">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header">
    <div class="site-container header-inner">
        <a class="site-brand" href="{{ route('home') }}" aria-label="{{ $siteName }} home"><img src="{{ $logo }}" alt="" width="37" height="37"><span>{{ $siteName }}</span><span class="brand-dot" aria-hidden="true"></span></a>
        <nav class="desktop-nav" aria-label="Main navigation">
            @foreach($nav as $key => [$label, $url])<a href="{{ $url }}" @if(($slug ?? '') === $key) aria-current="page" @endif>{{ $label }}</a>@endforeach
        </nav>
        <div class="header-actions">@auth<a class="button button-small" href="{{ route('dashboard') }}">Your dashboard <span aria-hidden="true">↗</span></a>@else<a class="login-link" href="{{ route('login') }}">Log in</a><a class="button button-small" href="{{ route('register') }}">Get started <span aria-hidden="true">↗</span></a>@endauth</div>
        <button class="menu-trigger" type="button" data-menu-open aria-controls="mobile-navigation" aria-expanded="false" aria-label="Open navigation">@include('public.partials.icon', ['icon' => 'menu'])</button>
        <noscript><a class="no-js-nav" href="#footer-navigation">Menu</a></noscript>
    </div>
</header>
<dialog id="mobile-navigation" class="mobile-navigation" aria-label="Mobile navigation">
    <div class="mobile-menu-top"><span class="site-brand">{{ $siteName }}<span class="brand-dot"></span></span><button type="button" data-menu-close class="icon-button" aria-label="Close navigation">@include('public.partials.icon', ['icon' => 'close'])</button></div>
    <nav aria-label="Mobile main navigation">@foreach($nav as $key => [$label, $url])<a href="{{ $url }}" @if(($slug ?? '') === $key) aria-current="page" @endif>{{ $label }} <span aria-hidden="true">↗</span></a>@endforeach</nav>
    <div class="mobile-menu-actions">@auth<a class="button" href="{{ route('dashboard') }}">Open dashboard</a>@else<a class="button" href="{{ route('register') }}">Get started</a><a class="button button-secondary" href="{{ route('login') }}">Log in</a>@endauth</div>
</dialog>
<main id="main-content" tabindex="-1">@yield('content')</main>
<footer class="site-footer" id="footer-navigation">
    <div class="site-container footer-grid">
        <div class="footer-intro"><a class="site-brand" href="{{ route('home') }}"><img src="{{ $logo }}" width="36" height="36" alt=""><span>{{ $siteName }}</span><span class="brand-dot"></span></a><p>A clearer path through learning,<br>research and knowledge sharing.</p><a class="text-link" href="{{ route('public.contact') }}">Talk to the team <span aria-hidden="true">↗</span></a></div>
        <nav aria-label="Product links"><h2>Product</h2><a href="{{ route('public.features') }}">Features</a><a href="{{ route('public.how-it-works') }}">How it works</a><a href="{{ route('register') }}">Getting started</a><a href="{{ route('public.page', ['page' => 'documentation']) }}">Product guide</a></nav>
        <nav aria-label="Company links"><h2>Company</h2><a href="{{ route('public.about') }}">About us</a><a href="{{ route('public.contact') }}">Contact us</a><a href="{{ route('public.faq') }}">FAQs</a></nav>
        <nav aria-label="Support links"><h2>Support</h2><a href="{{ route('public.help') }}">Help center</a><a href="{{ route('public.contact') }}">Support contact</a><a href="{{ route('public.page', ['page' => 'security']) }}">Security</a>@if($supportEmail)<a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>@endif</nav>
        <nav aria-label="Legal links"><h2>Legal</h2><a href="{{ route('public.privacy') }}">Privacy policy</a><a href="{{ route('public.terms') }}">Terms of service</a><a href="{{ route('public.cookies') }}">Cookie policy</a><a href="{{ route('public.disclaimer') }}">Disclaimer</a></nav>
    </div>
    <div class="site-container footer-bottom"><p>© {{ date('Y') }} {{ $siteName }}.</p><span>Make room for your next idea.</span><a href="#main-content">Back to top ↑</a></div>
</footer>
@include('partials.whatsapp-support')
</body>
</html>
