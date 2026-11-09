{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($parts as $part)
<sitemap><loc>{{ $part['loc'] }}</loc>@if($part['lastmod'])<lastmod>{{ \Illuminate\Support\Carbon::parse($part['lastmod'])->toAtomString() }}</lastmod>@endif</sitemap>
@endforeach
</sitemapindex>
