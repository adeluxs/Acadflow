<div class="feature-grid" data-search-items>
@foreach($features as $feature)
<article class="feature-card" id="{{ $feature['id'] }}" data-search-item data-category="{{ $feature['category'] }}" data-reveal><div class="feature-card-top"><span class="feature-icon icon-{{ $loop->index }}">@include('public.partials.icon', ['icon' => $feature['icon']])</span><span class="card-label">{{ $feature['category'] }}</span></div><h3>{{ $feature['title'] }}</h3><p>{{ $feature['description'] }}</p>@if($detailed ?? false)<ul class="detail-list">@foreach($feature['details'] as $detail)<li>@include('public.partials.icon', ['icon' => 'check']){{ $detail }}</li>@endforeach</ul><div class="use-case"><span>IN PRACTICE</span><p>{{ $feature['use'] }}</p></div>@else<a class="text-link" href="{{ route('public.features') }}#{{ $feature['id'] }}">Explore {{ strtolower($feature['category']) }} <span aria-hidden="true">↗</span></a>@endif</article>
@endforeach
</div>
