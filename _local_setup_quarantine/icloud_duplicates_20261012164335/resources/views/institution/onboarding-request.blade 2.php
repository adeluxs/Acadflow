@extends('layouts.app')
@section('title','Institution onboarding')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[.18em] text-indigo-600">Progressive institution adoption</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-950">Bring your institution or department to AcadFlow</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">Search the catalogue first. Existing institutions are claimed instead of recreated. Departments can also adopt AcadFlow independently while the wider institution is not ready.</p>
    </div>
    @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if(auth()->user()->account_type === 'university_representative' || auth()->user()->isUniversityAdmin())
    <section class="acad-card p-6" data-institution-search data-search-url="{{ route('institution.onboarding.search') }}" data-access-url="{{ route('institution.access') }}">
        <div><h2 class="text-xl font-bold">1. Search for your institution</h2><p class="mt-1 text-sm text-slate-500">Claim an existing catalogue record whenever possible. Partial names such as “Lagos” or “University of” are supported.</p></div>
        <div class="mt-5 flex gap-2">
            <input id="institution-search-input" type="search" autocomplete="off" placeholder="University of Lagos, UNILAG, institution code…" class="w-full rounded-xl border-slate-300" aria-describedby="institution-search-status">
            <button id="institution-search-button" type="button" class="acad-secondary-button">Search</button>
        </div>
        <div id="institution-search-status" class="mt-3 text-sm text-slate-500" role="status" aria-live="polite">Type at least 2 characters to search the catalogue.</div>
        <div id="institution-search-results" class="mt-4 space-y-3"></div>
        <button id="institution-search-retry" type="button" class="acad-secondary-button mt-3 hidden">Retry search</button>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <form id="institution-claim-form" method="POST" action="{{ route('institution.onboarding.request.store') }}" class="acad-card space-y-4 p-6">@csrf
            <input id="institution-request-type" type="hidden" name="request_type" value="{{ old('request_type','create_new') }}">
            <input id="institution-selected-id" type="hidden" name="university_id" value="{{ old('university_id') }}">
            <div>
                <h2 id="institution-request-title" class="text-lg font-bold">{{ old('request_type') === 'claim_existing' ? 'Claim existing institution' : 'Request new institution' }}</h2>
                <p id="institution-selected-wrap" class="mt-1 text-sm text-slate-500 {{ old('request_type') === 'claim_existing' ? '' : 'hidden' }}">Selected: <strong id="institution-selected-name">{{ $selectedUniversity?->name ?? 'existing institution' }}</strong></p>
            </div>
            <div id="new-institution-fields" class="grid gap-4 sm:grid-cols-2 {{ old('request_type') === 'claim_existing' ? 'hidden' : '' }}">
                <label class="sm:col-span-2 text-sm font-medium">Institution name<input id="new-institution-name" name="institution_name" value="{{ old('institution_name') }}" class="mt-1 w-full rounded-xl border-slate-300" @required(old('request_type','create_new') === 'create_new')></label>
                <label class="text-sm font-medium">Short name<input name="short_name" value="{{ old('short_name') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="text-sm font-medium">Code<input name="code" value="{{ old('code') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="text-sm font-medium">Type<select name="institution_type" class="mt-1 w-full rounded-xl border-slate-300"><option value="university" @selected(old('institution_type','university')==='university')>University</option><option value="polytechnic" @selected(old('institution_type')==='polytechnic')>Polytechnic</option><option value="college" @selected(old('institution_type')==='college')>College</option></select></label>
                <label class="text-sm font-medium">Ownership<select name="ownership" class="mt-1 w-full rounded-xl border-slate-300"><option value="">Select</option><option @selected(old('ownership')==='Federal')>Federal</option><option @selected(old('ownership')==='State')>State</option><option @selected(old('ownership')==='Private')>Private</option></select></label>
                <label class="text-sm font-medium">State<input name="state" value="{{ old('state') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="text-sm font-medium">Regulator<input name="regulator" value="{{ old('regulator') }}" placeholder="NUC / NBTE / NCCE" class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="sm:col-span-2 text-sm font-medium">Address<textarea name="address" rows="2" class="mt-1 w-full rounded-xl border-slate-300">{{ old('address') }}</textarea></label>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium">Official email<input type="email" name="email" value="{{ old('email') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="text-sm font-medium">Official phone<input name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="sm:col-span-2 text-sm font-medium">Website<input type="url" name="website" value="{{ old('website') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="sm:col-span-2 text-sm font-medium">Your official role<input name="representative_title" required value="{{ old('representative_title') }}" placeholder="Director of ICT, Registrar…" class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="sm:col-span-2 text-sm font-medium">Verification evidence / note<textarea name="evidence" rows="3" class="mt-1 w-full rounded-xl border-slate-300">{{ old('evidence') }}</textarea></label>
            </div>
            <div class="flex flex-wrap justify-between gap-2">
                <button id="institution-reset-selection" type="button" class="acad-secondary-button {{ old('request_type') === 'claim_existing' ? '' : 'hidden' }}">Request a different/new institution</button>
                <button class="acad-primary-button">Submit for verification</button>
            </div>
        </form>
        <aside class="acad-card p-6"><h2 class="font-bold">Your institution requests</h2><div class="mt-4 space-y-3">@forelse($requests as $item)<div class="rounded-xl border p-3"><div class="flex justify-between gap-3"><span class="font-semibold">{{ $item->institution_name }}</span><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold capitalize">{{ str_replace('_',' ',$item->status) }}</span></div><p class="mt-1 text-xs text-slate-500">{{ str_replace('_',' ',$item->request_type ?? 'create_new') }}</p>@if($item->review_note)<p class="mt-2 text-xs text-slate-500">{{ $item->review_note }}</p>@endif</div>@empty<p class="text-sm text-slate-500">No institution requests yet.</p>@endforelse</div></aside>
    </div>
    @endif

    @if(auth()->user()->account_type === 'department_representative' || in_array(auth()->user()->account_type,['lecturer','academic_staff'],true) || auth()->user()->isDepartmentAdmin())
    <section class="acad-card p-6"><h2 class="text-xl font-bold">Department-first adoption</h2><p class="mt-1 text-sm text-slate-500">Your department can use AcadFlow before the whole institution adopts it. Platform Admin verifies the department and representative.</p>
        <form method="POST" action="{{ route('institution.onboarding.department.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
            <label class="text-sm font-medium">Institution ID<input name="university_id" required value="{{ old('university_id',auth()->user()->university_id) }}" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Select/search institution ID"></label>
            <label class="text-sm font-medium">Faculty ID<input name="faculty_id" value="{{ old('faculty_id',auth()->user()->faculty_id) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-medium">Existing department ID<input name="department_id" value="{{ old('department_id',auth()->user()->department_id) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-medium">Department name (if missing)<input name="department_name" value="{{ old('department_name') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-medium">Department code<input name="department_code" value="{{ old('department_code') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-medium">Your role<input name="representative_title" required value="{{ old('representative_title') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="sm:col-span-2 text-sm font-medium">Evidence / note<textarea name="evidence" rows="3" class="mt-1 w-full rounded-xl border-slate-300"></textarea></label>
            <div class="sm:col-span-2 flex justify-end"><button class="acad-primary-button">Request department adoption</button></div>
        </form>
        @if($departmentRequests->isNotEmpty())<div class="mt-6 border-t pt-5"><h3 class="font-bold">Department requests</h3><div class="mt-3 grid gap-3 sm:grid-cols-2">@foreach($departmentRequests as $item)<div class="rounded-xl border p-3"><strong>{{ $item->department?->name ?: $item->department_name }}</strong><p class="mt-1 text-xs text-slate-500">{{ $item->university?->name }} · {{ str_replace('_',' ',$item->status) }}</p></div>@endforeach</div></div>@endif
    </section>
    @endif
</div>

<script>
(() => {
    const root = document.querySelector('[data-institution-search]');
    if (!root) return;

    const input = document.getElementById('institution-search-input');
    const button = document.getElementById('institution-search-button');
    const status = document.getElementById('institution-search-status');
    const results = document.getElementById('institution-search-results');
    const retry = document.getElementById('institution-search-retry');
    const requestType = document.getElementById('institution-request-type');
    const selectedId = document.getElementById('institution-selected-id');
    const selectedName = document.getElementById('institution-selected-name');
    const selectedWrap = document.getElementById('institution-selected-wrap');
    const requestTitle = document.getElementById('institution-request-title');
    const newFields = document.getElementById('new-institution-fields');
    const newName = document.getElementById('new-institution-name');
    const reset = document.getElementById('institution-reset-selection');
    const searchUrl = root.dataset.searchUrl;
    const accessUrl = root.dataset.accessUrl;

    let timer = null;
    let controller = null;
    let sequence = 0;
    let lastQuery = '';

    const setClaimMode = (item) => {
        requestType.value = 'claim_existing';
        selectedId.value = String(item.id);
        selectedName.textContent = item.name;
        selectedWrap.classList.remove('hidden');
        requestTitle.textContent = 'Claim existing institution';
        newFields.classList.add('hidden');
        newName.required = false;
        reset.classList.remove('hidden');
        document.getElementById('institution-claim-form')?.scrollIntoView({behavior:'smooth', block:'start'});
    };

    const setNewMode = () => {
        requestType.value = 'create_new';
        selectedId.value = '';
        selectedName.textContent = 'existing institution';
        selectedWrap.classList.add('hidden');
        requestTitle.textContent = 'Request new institution';
        newFields.classList.remove('hidden');
        newName.required = true;
        reset.classList.add('hidden');
    };

    const render = (items) => {
        results.replaceChildren();
        items.forEach((item) => {
            const card = document.createElement('div');
            card.className = 'rounded-2xl border p-4 sm:flex sm:items-center sm:justify-between';
            const meta = document.createElement('div');
            const name = document.createElement('p');
            name.className = 'font-bold';
            name.textContent = item.name || 'Institution';
            const detail = document.createElement('p');
            detail.className = 'mt-1 text-xs text-slate-500';
            detail.textContent = `${item.type || 'Institution'} · ${item.state || 'Location not listed'} · ${String(item.adoption_status || 'unclaimed').replaceAll('_',' ')}`;
            meta.append(name, detail);
            const choose = document.createElement('button');
            choose.type = 'button';
            choose.className = 'mt-3 acad-primary-button sm:mt-0';
            const active = item.adoption_status === 'active';
            choose.textContent = active ? 'Request access instead' : 'Claim this institution';
            choose.addEventListener('click', () => {
                if (active) {
                    const separator = accessUrl.includes('?') ? '&' : '?';
                    window.location.assign(`${accessUrl}${separator}university_id=${encodeURIComponent(item.id)}`);
                    return;
                }
                setClaimMode(item);
            });
            card.append(meta, choose);
            results.appendChild(card);
        });
    };

    const search = async () => {
        const query = input.value.trim();
        lastQuery = query;
        retry.classList.add('hidden');
        if (query.length < 2) {
            controller?.abort();
            results.replaceChildren();
            status.textContent = 'Type at least 2 characters to search the catalogue.';
            return;
        }

        controller?.abort();
        controller = new AbortController();
        const current = ++sequence;
        button.disabled = true;
        status.textContent = 'Searching institutions…';

        try {
            const url = new URL(searchUrl, window.location.origin);
            url.searchParams.set('q', query);
            const response = await fetch(url.toString(), {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, signal:controller.signal});
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message || 'Institution search could not be completed.');
            if (current !== sequence) return;
            const items = Array.isArray(data.data) ? data.data : [];
            render(items);
            status.textContent = items.length ? `${items.length} matching institution${items.length === 1 ? '' : 's'} found.` : 'No catalogue match found. You can request a new institution below.';
        } catch (error) {
            if (error?.name === 'AbortError' || current !== sequence) return;
            results.replaceChildren();
            status.textContent = 'Institution search could not be completed. Check your connection and try again.';
            retry.classList.remove('hidden');
        } finally {
            if (current === sequence) button.disabled = false;
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(search, 350);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') { event.preventDefault(); clearTimeout(timer); search(); }
    });
    button.addEventListener('click', () => { clearTimeout(timer); search(); });
    retry.addEventListener('click', () => { input.value = lastQuery; search(); });
    reset.addEventListener('click', setNewMode);
})();
</script>
@endsection
