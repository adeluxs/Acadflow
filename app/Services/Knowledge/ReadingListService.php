<?php

namespace App\Services\Knowledge;

use App\Models\AcademicReference;
use App\Models\KnowledgePublication;
use App\Models\ReadingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ReadingListService
{
    public function create(User $owner, array $data): ReadingList
    {
        return ReadingList::create(['university_id' => $owner->university_id, 'owner_id' => $owner->id, 'research_project_id' => $data['research_project_id'] ?? null, 'course_id' => $data['course_id'] ?? null, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'list_type' => $data['list_type'] ?? 'private', 'visibility' => $data['visibility'] ?? 'private', 'is_collaborative' => (bool) ($data['is_collaborative'] ?? false)]);
    }

    public function add(ReadingList $list, Model $item, User $actor, ?string $note = null): void
    {
        $this->authorizeEdit($list, $actor);
        $list->items()->firstOrCreate(['item_type' => $item->getMorphClass(), 'item_id' => $item->getKey()], ['added_by' => $actor->id, 'position' => ((int) $list->items()->max('position')) + 1, 'status' => 'unread', 'note' => $note]);
        if ($list->research_project_id && $item instanceof KnowledgePublication) {
            $reference = AcademicReference::firstOrCreate(['owner_id' => $actor->id, 'title' => $item->title], ['university_id' => $actor->university_id, 'authors' => [$item->creator?->full_name], 'publication_year' => $item->published_at?->year, 'source_type' => 'knowledge_hub', 'url' => route('knowledge.show',$item), 'metadata' => ['knowledge_publication_uuid' => $item->uuid]]);
            $list->researchProject?->referenceLinks()->firstOrCreate(['academic_reference_id' => $reference->id], ['purpose' => 'reading_list_import']);
        }
    }

    public function syncMember(ReadingList $list, User $actor, User $member, string $role): void
    {
        abort_unless($list->owner_id === $actor->id || $actor->isAdmin(), 403);
        abort_unless($list->is_collaborative, 422, 'Enable collaborative mode before adding members.');
        abort_if($member->id === $list->owner_id, 422, 'The owner already has full access.');
        if ($list->university_id && ! $actor->isSuperAdmin()) abort_unless($member->university_id === $list->university_id, 422, 'Collaborators must belong to the same institution.');
        $list->members()->updateOrCreate(['user_id' => $member->id], ['role' => $role]);
    }

    public function removeMember(ReadingList $list, User $actor, User $member): void
    {
        abort_unless($list->owner_id === $actor->id || $actor->isAdmin(), 403);
        $list->members()->where('user_id', $member->id)->delete();
    }

    public function exportRows(ReadingList $list, ?User $user): array
    {
        $this->authorizeView($list, $user);
        $list->loadMissing(['items.item', 'owner', 'researchProject']);

        return $list->items->map(fn ($row) => [
            'position' => $row->position,
            'title' => $row->item?->title ?? class_basename($row->item_type).' #'.$row->item_id,
            'type' => class_basename($row->item_type),
            'status' => $row->status,
            'note' => $row->note,
            'completed_at' => $row->completed_at?->toIso8601String(),
            'url' => $row->item instanceof KnowledgePublication ? route('knowledge.show', $row->item) : null,
        ])->all();
    }

    public function authorizeView(ReadingList $list, ?User $user): void
    {
        if ($list->visibility === 'public') return;
        abort_unless($user && ($list->owner_id === $user->id || $user->isAdmin() || $list->members()->where('user_id',$user->id)->exists()), 403);
    }

    private function authorizeEdit(ReadingList $list, User $actor): void
    {
        abort_unless($list->owner_id === $actor->id || $actor->isAdmin() || ($list->is_collaborative && $list->members()->where('user_id',$actor->id)->where('role','editor')->exists()), 403);
    }
}
