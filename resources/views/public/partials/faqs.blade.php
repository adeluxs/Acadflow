<div class="faq-list" data-search-items>
@foreach($faqs as $faq)<details class="faq-item" data-search-item data-category="{{ $faq['topic'] }}"><summary><span>@if($showTopics ?? false)<small>{{ $faq['topic'] }}</small>@endif{{ $faq['question'] }}</span><span class="faq-plus" aria-hidden="true">+</span></summary><div class="faq-answer"><p>{{ $faq['answer'] }}</p></div></details>@endforeach
</div>
