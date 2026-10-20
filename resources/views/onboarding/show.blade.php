@extends('layouts.onboarding')
@section('title', 'Set up your AcadSyntra workspace')
@section('content')
@php
    $progress = (int) round(($stepNumber / count($steps)) * 100);
    $notificationData = $data['notification_preferences'] ?? [];
@endphp
<div class="studio-shell grid gap-8 lg:grid-cols-[290px_minmax(0,1fr)]">
    <aside>
        <div class="sticky top-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold text-blue-700">Welcome, {{ $user->first_name }}</p>
            <h1 class="mt-2 text-2xl font-black">Build your workspace</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">Your answers personalize research discovery, communities, events and the tools shown on your dashboard.</p>
            <div role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Onboarding progress" class="mt-6 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-700" style="width: {{ $progress }}%"></div></div>
            <p class="mt-2 text-xs font-semibold text-slate-500">Step {{ $stepNumber }} of {{ count($steps) }} · {{ $progress }}%</p>
            <p class="mt-3 text-xs leading-5 text-slate-500">Progress saves after each step. You can sign out and resume here.</p><ol class="mt-6 space-y-3">
                @foreach($steps as $number => $key)
                    <li @if($number === $stepNumber) aria-current="step" @endif class="flex items-center gap-3 text-sm {{ $number === $stepNumber ? 'font-bold text-blue-800' : ($number < $stepNumber ? 'text-emerald-700' : 'text-slate-500') }}">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full {{ $number === $stepNumber ? 'bg-blue-700 text-white' : ($number < $stepNumber ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100') }}">{{ $number < $stepNumber ? '✓' : $number }}</span>
                        {{ ucwords(str_replace('_', ' ', $key)) }}
                    </li>
                @endforeach
            </ol>
        </div>
    </aside>

    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-9">
        @if($step === 'path')
            <p class="text-sm font-semibold text-blue-700">Choose your path</p>
            <h2 class="mt-2 text-3xl font-black">How will you use AcadSyntra?</h2>
            <p class="mt-2 text-slate-600">This controls recommendations, not your institutional permissions. Independent users do not need a university.</p>
            <form method="POST" action="{{ route('onboarding.save', 'path') }}" class="mt-7">@csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach($accountPaths as $value => $label)
                        <label class="cursor-pointer rounded-2xl border border-slate-200 p-4 transition hover:border-blue-400 hover:bg-blue-50/50 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                            <input type="radio" name="path" value="{{ $value }}" class="sr-only" @checked(old('path', $state->path) === $value) required>
                            <span class="font-bold">{{ $label }}</span>
                            <span class="mt-1 block text-sm text-slate-500">{{ match($value) {
                                'student' => 'Courses, assignments, research projects and campus opportunities.',
                                'lecturer' => 'Teaching, supervision, publishing and academic communities.',
                                'researcher' => 'Research Studio, references, publishing and citation impact.',
                                'university_representative', 'department_representative' => 'Institutional presence, moderation and academic coordination.',
                                'independent_professional' => 'Publish, collaborate and build a professional knowledge profile.',
                                'author_publisher' => 'Create publications and distribute approved educational resources.',
                                'research_discovery' => 'Follow topics, researchers and trusted academic publications.',
                                'community_events' => 'Join discussions, events, groups and academic challenges.',
                                'organisation' => 'Represent a journal, research group or knowledge organisation.',
                                default => 'Use the platform with a flexible, independent profile.'
                            } }}</span>
                        </label>
                    @endforeach
                </div>
                @include('onboarding._actions', ['optional' => false])
            </form>
        @elseif($step === 'profile')
            <p class="text-sm font-semibold text-blue-700">Public identity</p>
            <h2 class="mt-2 text-3xl font-black">Tell the academic network who you are</h2>
            <form method="POST" action="{{ route('onboarding.save', 'profile') }}" enctype="multipart/form-data" class="mt-7 space-y-5">@csrf
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><label for="first_name" class="mb-2 block text-sm font-semibold">First name</label><input id="first_name" name="first_name" required value="{{ old('first_name', $data['first_name'] ?? '') }}" class="w-full rounded-2xl border-slate-300 px-4 py-3"></div>
                    <div><label for="last_name" class="mb-2 block text-sm font-semibold">Last name</label><input id="last_name" name="last_name" required value="{{ old('last_name', $data['last_name'] ?? '') }}" class="w-full rounded-2xl border-slate-300 px-4 py-3"></div>
                </div>
                <div><label class="mb-2 block text-sm font-semibold">Username</label><div class="flex rounded-2xl border border-slate-300 bg-white focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600"><span class="px-4 py-3 text-slate-400">@</span><input name="username" required value="{{ old('username', $data['username'] ?? '') }}" class="min-w-0 flex-1 border-0 bg-transparent px-0 py-3 focus:ring-0"></div></div>
                <div><label for="avatar" class="mb-2 block text-sm font-semibold">Profile photo <span class="font-normal text-slate-500">(optional)</span></label><input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3"><p class="mt-1 text-xs text-slate-500">JPG, PNG or WebP up to 5 MB. Files are security-scanned.</p></div>
                <div><label for="headline" class="mb-2 block text-sm font-semibold">Professional headline</label><input id="headline" name="headline" value="{{ old('headline', $data['headline'] ?? '') }}" placeholder="Researcher in renewable energy · Lecturer · Product designer" class="w-full rounded-2xl border-slate-300 px-4 py-3"></div>
                <div><label for="biography" class="mb-2 block text-sm font-semibold">Short biography</label><textarea id="biography" name="biography" rows="5" class="w-full rounded-2xl border-slate-300 px-4 py-3" placeholder="Describe your work, interests and what you hope to contribute.">{{ old('biography', $data['biography'] ?? '') }}</textarea></div>
                <div class="grid gap-5 sm:grid-cols-3">
                    <div><label for="country_code" class="mb-2 block text-sm font-semibold">Country code</label><input id="country_code" name="country_code" maxlength="2" value="{{ old('country_code', $data['country_code'] ?? '') }}" placeholder="NG" class="w-full rounded-2xl border-slate-300 px-4 py-3 uppercase"></div>
                    <div class="sm:col-span-2"><label for="location" class="mb-2 block text-sm font-semibold">City or location</label><input id="location" name="location" value="{{ old('location', $data['location'] ?? '') }}" placeholder="Lagos, Nigeria" class="w-full rounded-2xl border-slate-300 px-4 py-3"></div>
                </div>
                <div><label for="phone" class="mb-2 block text-sm font-semibold">Phone (optional)</label><input id="phone" name="phone" value="{{ old('phone', $data['phone'] ?? '') }}" autocomplete="tel" class="w-full rounded-2xl border-slate-300 px-4 py-3"></div>
                @include('onboarding._actions', ['optional' => false])
            </form>
        @elseif($step === 'affiliation')
            <p class="text-sm font-semibold text-blue-700">Institutional connection</p>
            <h2 class="mt-2 text-3xl font-black">Connect an institution when it applies</h2>
            <p class="mt-2 text-slate-600">Independent users can skip this step. Selecting an institution does not automatically grant staff or administrator access.</p>
            <form method="POST" action="{{ route('onboarding.save', 'affiliation') }}" class="mt-7 space-y-5" id="affiliation-form">@csrf
                <div><label for="university_id" class="mb-2 block text-sm font-semibold">University</label><select id="university_id" name="university_id" class="w-full rounded-2xl border-slate-300 px-4 py-3"><option value="">No institution / not listed</option>@foreach($universities as $university)<option value="{{ $university->id }}" @selected((int) old('university_id', $data['university_id'] ?? 0) === $university->id)>{{ $university->name }}</option>@endforeach</select></div>
                <div><label for="faculty_id" class="mb-2 block text-sm font-semibold">Faculty or school</label><select id="faculty_id" name="faculty_id" class="w-full rounded-2xl border-slate-300 px-4 py-3"><option value="">Not applicable</option>@foreach($universities as $university)@foreach($university->faculties as $faculty)<option value="{{ $faculty->id }}" data-university="{{ $university->id }}" @selected((int) old('faculty_id', $data['faculty_id'] ?? 0) === $faculty->id)>{{ $faculty->name }}</option>@endforeach @endforeach</select></div>
                <div><label for="department_id" class="mb-2 block text-sm font-semibold">Department</label><select id="department_id" name="department_id" class="w-full rounded-2xl border-slate-300 px-4 py-3"><option value="">Not applicable</option>@foreach($universities as $university)@foreach($university->faculties as $faculty)@foreach($faculty->departments as $department)<option value="{{ $department->id }}" data-faculty="{{ $faculty->id }}" @selected((int) old('department_id', $data['department_id'] ?? 0) === $department->id)>{{ $department->name }}</option>@endforeach @endforeach @endforeach</select></div>
                @include('onboarding._actions', ['optional' => true])
            </form>
        @elseif($step === 'interests')
            <p class="text-sm font-semibold text-blue-700">Personalization</p>
            <h2 class="mt-2 text-3xl font-black">Choose the knowledge you care about</h2>
            <p class="mt-2 text-slate-600">Use commas to separate multiple items. You can change these later.</p>
            <form method="POST" action="{{ route('onboarding.save', 'interests') }}" class="mt-7 space-y-5" data-tag-form>@csrf
                <div class="grid gap-5 sm:grid-cols-2"><div><label for="programme" class="mb-2 block text-sm font-semibold">Programme or discipline</label><input id="programme" name="programme" value="{{ old('programme', $data['programme'] ?? '') }}" class="w-full rounded-2xl border-slate-300 px-4 py-3" placeholder="Computer Science"></div><div><label for="academic_level" class="mb-2 block text-sm font-semibold">Academic level</label><input id="academic_level" name="academic_level" value="{{ old('academic_level', $data['academic_level'] ?? '') }}" list="research-level-options" class="w-full rounded-2xl border-slate-300 px-4 py-3" placeholder="ND, HND, undergraduate, masters, doctoral"><datalist id="research-level-options">@foreach(config('research_methodologies.levels') as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</datalist></div></div>
                @foreach([
                    'research_interests' => ['Research interests', 'machine learning, public health, renewable energy'],
                    'skills' => ['Skills', 'data analysis, academic writing, Laravel'],
                    'topic_interests' => ['Topics to follow', 'education technology, economics, law'],
                    'community_interests' => ['Communities to discover', 'research methods, software engineering'],
                    'event_interests' => ['Event interests', 'seminars, workshops, conferences'],
                ] as $field => [$label, $placeholder])
                    <div><label for="{{ $field }}-text" class="mb-2 block text-sm font-semibold">{{ $label }}</label><input id="{{ $field }}-text" name="{{ $field }}_text" data-array-field="{{ $field }}" value="{{ old($field.'_text', implode(', ', (array) old($field, $data[$field] ?? []))) }}" class="w-full rounded-2xl border-slate-300 px-4 py-3" placeholder="{{ $placeholder }}"><div data-hidden-for="{{ $field }}"></div></div>
                @endforeach
                @include('onboarding._actions', ['optional' => true])
            </form>
        @elseif($step === 'preferences')
            <p class="text-sm font-semibold text-blue-700">Privacy and notifications</p>
            <h2 class="mt-2 text-3xl font-black">Control what is visible and what reaches you</h2>
            <form method="POST" action="{{ route('onboarding.save', 'preferences') }}" class="mt-7 space-y-6">@csrf
                <div><label for="profile_visibility" class="mb-2 block text-sm font-semibold">Profile visibility</label><select id="profile_visibility" name="profile_visibility" class="w-full rounded-2xl border-slate-300 px-4 py-3"><option value="public" @selected(old('profile_visibility', $data['profile_visibility'] ?? 'public') === 'public')>Public — discoverable across AcadSyntra</option><option value="institution" @selected(old('profile_visibility', $data['profile_visibility'] ?? '') === 'institution')>Institution — visible to signed-in institutional users</option><option value="private" @selected(old('profile_visibility', $data['profile_visibility'] ?? '') === 'private')>Private — visible only to me and authorised collaborators</option></select></div>
                <div class="space-y-3">
                    @foreach([
                        'email_notifications' => ['Email notifications', 'Important invitations, decisions and account alerts', $notificationData['email'] ?? true],
                        'in_app_notifications' => ['In-app notifications', 'Activity, mentions, replies and workflow updates', $notificationData['in_app'] ?? true],
                        'event_reminders' => ['Event reminders', 'Upcoming events and registration deadlines', $notificationData['event_reminders'] ?? true],
                        'research_updates' => ['Research updates', 'Supervisor comments, corrections and approvals', $notificationData['research_updates'] ?? true],
                        'community_updates' => ['Community updates', 'Membership requests, announcements and discussions', $notificationData['community_updates'] ?? true],
                        'personalized_recommendations' => ['Personalized recommendations', 'Relevant publications, communities, events and learning paths', $notificationData['personalized_recommendations'] ?? true],
                    ] as $field => [$label, $copy, $checked])
                        <label class="flex items-start gap-4 rounded-2xl border border-slate-200 p-4"><input type="checkbox" name="{{ $field }}" value="1" class="mt-1 rounded border-slate-300 text-blue-700" @checked(old($field, $checked))><span><strong class="block">{{ $label }}</strong><span class="mt-1 block text-sm text-slate-500">{{ $copy }}</span></span></label>
                    @endforeach
                </div>
                @include('onboarding._actions', ['optional' => true])
            </form>
        @else
            <p class="text-sm font-semibold text-blue-700">Final review</p>
            <h2 class="mt-2 text-3xl font-black">Your AcadSyntra profile is ready to create</h2>
            <div class="mt-7 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl bg-slate-50 p-5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Account path</p><p class="mt-2 font-bold">{{ $accountPaths[$state->path] ?? 'General user' }}</p></div>
                <div class="rounded-2xl bg-slate-50 p-5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Profile</p><p class="mt-2 font-bold">{{ $data['first_name'] ?? '' }} {{ $data['last_name'] ?? '' }}</p><p class="text-sm text-slate-500">@{{ $data['username'] ?? '' }}</p></div>
                <div class="rounded-2xl bg-slate-50 p-5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Affiliation</p><p class="mt-2 font-bold">{{ optional($universities->firstWhere('id', (int) ($data['university_id'] ?? 0)))->name ?: 'Independent / not specified' }}</p></div>
                <div class="rounded-2xl bg-slate-50 p-5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Interests</p><p class="mt-2 text-sm text-slate-700">{{ implode(', ', array_slice(array_merge($data['research_interests'] ?? [], $data['topic_interests'] ?? []), 0, 8)) ?: 'You can add interests later.' }}</p></div>
            </div>
            <div class="mt-7 flex flex-wrap items-center justify-between gap-3">
                <form method="POST" action="{{ route('onboarding.back') }}">@csrf<button class="rounded-2xl border border-slate-300 px-5 py-3 font-bold text-slate-700">Back</button></form>
                <form method="POST" action="{{ route('onboarding.complete') }}" class="w-full space-y-4 sm:w-auto">@csrf
                    @if(in_array($state->path, ['student', 'lecturer', 'researcher']) && \App\Services\FeatureAccessService::effectiveStatus('research_studio', $user->university_id) === \App\Services\FeatureAccessService::STATUS_ENABLED)
                    <label class="block text-sm font-semibold">Your next step<select name="next_action" class="mt-2 w-full rounded-xl border-slate-300"><option value="dashboard">Explore my personalised workspace</option><option value="research">Create my first research project</option><option value="final_year">Start a final-year project</option></select></label>
                    <p class="text-xs leading-5 text-slate-500 max-w-sm">Research Studio helps you plan, write and organise verified sources. Choose a methodology and create a real project after setup.</p>@endif
                    <button class="rounded-2xl bg-blue-700 px-6 py-3 font-bold text-white shadow-lg shadow-blue-700/20 hover:bg-blue-800">Complete onboarding</button></form>
            </div>
        @endif
    </section>
</div>
@endsection
@push('scripts')
<script>
    const university = document.getElementById('university_id');
    const faculty = document.getElementById('faculty_id');
    const department = document.getElementById('department_id');
    function filterAffiliations() {
        if (!university || !faculty || !department) return;
        const universityId = university.value;
        [...faculty.options].forEach((option, index) => { if(index) option.hidden = !!universityId && option.dataset.university !== universityId; });
        if (faculty.selectedOptions[0]?.hidden) faculty.value = '';
        const facultyId = faculty.value;
        [...department.options].forEach((option, index) => { if(index) option.hidden = !!facultyId && option.dataset.faculty !== facultyId; });
        if (department.selectedOptions[0]?.hidden) department.value = '';
    }
    university?.addEventListener('change', filterAffiliations);
    faculty?.addEventListener('change', filterAffiliations);
    filterAffiliations();

    document.querySelectorAll('form[data-tag-form]').forEach((form) => {
        const syncTags = () => {
            form.querySelectorAll('[data-array-field]').forEach((input) => {
                const holder = form.querySelector(`[data-hidden-for="${input.dataset.arrayField}"]`);
                holder.innerHTML = '';
                input.value.split(',').map(value => value.trim()).filter(Boolean).forEach((value) => {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden'; hidden.name = `${input.dataset.arrayField}[]`; hidden.value = value; holder.appendChild(hidden);
                });
            });
        };
        form.addEventListener('submit', syncTags);
        syncTags();
    });
</script>
@endpush
