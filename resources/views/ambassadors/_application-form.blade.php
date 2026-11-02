<section class="amb-card amb-section" id="apply">
    <h2>{{ $application ? 'Update and resubmit your application' : 'Tell us about your campus' }}</h2>
    <p class="amb-muted">Students who are course reps, student leaders or volunteers can apply. The platform team reviews every application before activating referral tools.@if(! empty($profile)) We filled in what your profile already tells us; check and adjust it.@endif</p>
    <form method="POST" action="{{ route('ambassadors.apply') }}">
        @csrf
        <div class="amb-fields">
            @foreach(['institution' => ['Institution', 200, true], 'faculty' => ['Faculty (optional)', 200, false], 'department' => ['Department / course', 200, true], 'study_level' => ['Level / year of study', 80, true], 'phone' => ['Contact phone with country code', 40, true]] as $name => [$label, $limit, $required])
                <label class="amb-field" for="amb-{{ $name }}">{{ $label }}<input id="amb-{{ $name }}" name="{{ $name }}" value="{{ old($name, $application?->{$name} ?? ($profile[$name] ?? '')) }}" maxlength="{{ $limit }}" @if($required) required @endif @if($name === 'phone') type="tel" autocomplete="tel" @endif>@error($name)<small class="amb-error">{{ $message }}</small>@enderror</label>
            @endforeach
            <label class="amb-field" for="amb-role">Your role<select id="amb-role" name="leadership_role" required>@foreach(['course_rep' => 'Course representative', 'student_leader' => 'Student leader', 'student_volunteer' => 'Student volunteer'] as $value => $label)<option value="{{ $value }}" @selected(old('leadership_role', $application?->leadership_role) === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="amb-field" for="amb-size">Students you could help<input id="amb-size" type="number" name="community_size" min="1" max="10000" value="{{ old('community_size', $application?->community_size) }}" required><small>A realistic estimate, not a signup commitment.</small></label>
            <label class="amb-field amb-wide" for="amb-social">Public social or professional profile (optional)<input id="amb-social" type="url" name="social_profile_url" value="{{ old('social_profile_url', $application?->social_profile_url) }}" maxlength="500" placeholder="https://"><small>Only if it helps us understand your community work. Leave blank if you prefer.</small>@error('social_profile_url')<small class="amb-error">{{ $message }}</small>@enderror</label>
            <label class="amb-field amb-wide" for="amb-statement">Your first-month plan<textarea id="amb-statement" name="statement" rows="4" minlength="30" maxlength="3000" required>{{ old('statement', $application?->statement) }}</textarea><small>Why do you want to be an ambassador? Tell us who you help and one useful activity you would organise.</small>@error('statement')<small class="amb-error">{{ $message }}</small>@enderror</label>
            <label class="amb-check amb-wide"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))><span>I am a student and my application is accurate. I have read the <a href="{{ route('public.terms') }}" target="_blank" rel="noopener">terms</a> and <a href="{{ route('public.privacy') }}" target="_blank" rel="noopener">privacy policy</a>. I will keep participation voluntary, disclose programme rewards, respect classmates’ privacy, and avoid spam, fake accounts or unapproved university endorsement. I understand rewards require review and approval.</span></label>
        </div>
        @error('application')<p class="amb-error" role="alert">{{ $message }}</p>@enderror
        <div class="amb-actions"><button class="amb-button amb-button-primary">Submit application</button></div>
    </form>
</section>

<p class="amb-muted">To protect the programme from duplicate accounts, we use a first-party browser cookie and hashed signup signals for manual referral review. Shared campus devices or Wi-Fi are not automatically rejected.</p>
