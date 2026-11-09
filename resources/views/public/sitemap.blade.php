{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($urls as $url)
@php($entry = is_array($url) ? $url : ['loc' => $url, 'lastmod' => null])
<url><loc>{{ $entry['loc'] }}</loc>@if($entry['lastmod'])<lastmod>{{ \Illuminate\Support\Carbon::parse($entry['lastmod'])->toAtomString() }}</lastmod>@endif</url>
@endforeach
</urlset>
