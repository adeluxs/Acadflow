<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeFollow;
use App\Models\User;
use App\Services\Knowledge\KnowledgeNotificationService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Follow or unfollow a Knowledge Hub creator to hear when they publish. */
class KnowledgeCreatorFollowController extends Controller
{
    public function toggle(Request $request, User $creator): RedirectResponse
    {
        abort_unless((bool) SettingService::getGlobal('knowledge_follows_enabled', true), 404);
        $user = $request->user();
        abort_if($creator->id === $user->id, 422, 'You cannot follow yourself.');
        abort_unless($creator->is_active && $creator->knowledgePublications()->where('status', 'published')->exists(), 404);

        $existing = KnowledgeFollow::query()->where('follower_id', $user->id)->whereIn('target_type', KnowledgeNotificationService::CREATOR_TARGETS)->where('target_id', $creator->id);
        if ((clone $existing)->exists()) {
            $existing->delete();

            return back()->with('success', 'You unfollowed '.$creator->first_name.'.');
        }
        KnowledgeFollow::firstOrCreate(['follower_id' => $user->id, 'target_type' => 'creator', 'target_id' => $creator->id], ['created_at' => now()]);

        return back()->with('success', 'You follow '.$creator->first_name.'. We\'ll let you know when they publish something new.');
    }
}
