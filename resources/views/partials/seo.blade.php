{{-- Search, social-card and AI-engine metadata for one public page. --}}
<meta name="description" content="{{ $seo['description'] }}">
<meta name="robots" content="{{ $seo['robots'] ?? 'index, follow' }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
<meta property="og:site_name" content="{{ app(\App\Services\Seo\KnowledgeSeo::class)->siteName() }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta property="og:image" content="{{ $seo['image'] }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="en_NG">
@foreach(array_filter($seo['article'] ?? []) as $property => $value)
<meta property="article:{{ $property }}" content="{{ $value }}">
@endforeach
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
<meta name="twitter:image" content="{{ $seo['image'] }}">
@if(! empty($seo['schema']))
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => array_values($seo['schema'])], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
