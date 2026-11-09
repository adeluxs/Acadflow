{{-- Follow a creator to get in-app, email and push alerts when they publish. --}}
@auth
    @if(auth()->id() !== $creatorUser->id && (bool) \App\Services\SettingService::getGlobal('knowledge_follows_enabled', true))
        @php($followsCreator = $followingCreator ?? \App\Models\KnowledgeFollow::query()->where('follower_id', auth()->id())->whereIn('target_type', \App\Services\Knowledge\KnowledgeNotificationService::CREATOR_TARGETS)->where('target_id', $creatorUser->id)->exists())
        <form method="POST" action="{{ route('knowledge.creators.follow', $creatorUser) }}" class="{{ $followClass ?? '' }}">
            @csrf
            <button class="rounded-xl px-4 py-2 text-sm font-bold transition {{ $followsCreator ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}" title="{{ $followsCreator ? 'You get alerts when '.$creatorUser->first_name.' publishes' : 'Get alerts when '.$creatorUser->first_name.' publishes' }}">
                {{ $followsCreator ? '✓ Following '.$creatorUser->first_name : '+ Follow '.$creatorUser->first_name }}
            </button>
        </form>
    @endif
@endauth
