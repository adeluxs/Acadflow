@extends('layouts.public')
@push('styles')<link rel="stylesheet" href="{{ asset('css/ambassadors.css') }}">@endpush
@php
    // One call to action that matches where this visitor is in the programme.
    $state = auth()->check() ? ($ambassadorApplication?->status ?? 'none') : 'guest';
    $isStudent = auth()->user()?->isStudent();
    [$ctaLabel, $ctaUrl] = match (true) {
        $state === 'guest' => ['Become a Campus Ambassador', route('ambassadors.join')],
        auth()->user()?->isSuperAdmin() => ['Manage the programme', route('admin.ambassadors.index')],
        $state === 'approved' => ['Open ambassador dashboard', route('ambassadors.index')],
        $state === 'pending' => ['View application status', route('ambassadors.index')],
        $state === 'suspended' => ['View programme status', route('ambassadors.index')],
        in_array($state, ['rejected', 'withdrawn'], true) => ['Update and resubmit your application', route('ambassadors.index').'#apply'],
        (bool) $isStudent => ['Become a Campus Ambassador', route('ambassadors.index').'#apply'],
        default => ['View your Campus Ambassador page', route('ambassadors.index')],
    };
@endphp
@section('content')
@include('public.partials.page-heading', ['eyebrow' => 'CAMPUS AMBASSADOR PROGRAMME'])
<section class="section section-topless"><div class="site-container amb-shell">
    <div class="amb-hero">
        <p class="amb-eyebrow">Bring smarter academic collaboration to your campus</p>
        <h2>What is the AcadSyntra Campus Ambassador Programme?</h2>
        <p>The programme brings together students who help their academic communities discover AcadSyntra: introducing classmates to the study, research and collaboration tools, supporting new users, and sharing honest feedback with our team so more students, departments and institutions benefit.</p>
        <div class="amb-actions"><a class="amb-button amb-button-primary" href="{{ $ctaUrl }}">{{ $ctaLabel }}</a><a class="amb-button" href="#how-to-join">How to join</a>@guest<a class="amb-button" href="{{ route('ambassadors.join', ['account' => 'existing']) }}">I already have an account</a>@endguest</div>
    </div>

    <div class="amb-grid">
        <article class="amb-card"><h2>What do Campus Ambassadors do?</h2><ul>
            <li>Raise awareness of AcadSyntra among classmates.</li>
            <li>Help students understand and start using the study tools.</li>
            <li>Introduce the platform to course groups and student communities.</li>
            <li>Run approved campus activities, such as short demos and study sessions.</li>
            <li>Share structured student feedback with the AcadSyntra team.</li>
            <li>Encourage adoption while respecting your institution’s and department’s own approval processes.</li>
        </ul></article>
        <article class="amb-card"><h2>Why become a Campus Ambassador?</h2><ul>
            <li>Build leadership, communication and community-building experience.</li>
            <li>Get recognised for real contributions to your campus community.</li>
            <li>Take part in educational technology advocacy and shape the product with your feedback.</li>
            <li>Access official programme activities and resources.</li>
            <li>Be considered for discretionary incentives after review, such as study wallet credit, data allowances or recognition.</li>
        </ul><p class="amb-muted">There is no fixed commission, salary or guaranteed reward. Rewards are reviewed individually and are never earned by signups alone.</p></article>
    </div>

    <section class="amb-card amb-section" id="how-to-join"><h2>How to become an ambassador</h2>
        <ol class="amb-public-steps">
            <li>Create or sign in to your free AcadSyntra student account and complete onboarding.</li>
            <li>Open your Campus Ambassador page and complete the short application.</li>
            <li>Submit it for review. You can withdraw a pending application at any time.</li>
            <li>Receive the team’s decision and feedback on your Campus Ambassador page.</li>
            <li>Once approved, open your ambassador dashboard with your invitation link and starter guide.</li>
            <li>Take part in programme activities and report what you did.</li>
        </ol>
        <div class="amb-actions"><a class="amb-button amb-button-primary" href="{{ $ctaUrl }}">{{ $ctaLabel }}</a></div>
    </section>

    <section class="amb-card amb-section" id="eligibility"><h2>Who can apply</h2><ul>
        <li>You have an active AcadSyntra <strong>student</strong> account with completed onboarding.</li>
        <li>You are a course representative, student leader or student volunteer, or you regularly help classmates.</li>
        <li>You agree to keep participation voluntary, disclose that you may earn programme rewards, respect classmates’ privacy and avoid spam, fake accounts and unapproved claims of university endorsement.</li>
        <li>Your institution does not need to be on AcadSyntra yet.</li>
    </ul><p class="amb-muted">Being an ambassador does not give access to student records, institution or department administration, or the power to approve memberships.</p></section>

    <section class="amb-card amb-section amb-faq" id="faq"><h2>Frequently asked questions</h2>
        @foreach($faqs as $faq)<details><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>@endforeach
    </section>

    <div class="amb-actions"><a class="amb-button amb-button-primary" href="{{ $ctaUrl }}">{{ $ctaLabel }}</a><a href="{{ route('public.contact') }}">Talk to the team</a></div>
</div></section>
@endsection
