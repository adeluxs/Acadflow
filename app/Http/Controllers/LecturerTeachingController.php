<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AiMode;
use App\Services\Ai\AiRuntimeConfigService;
use App\Services\FeatureAccessService;
use App\Services\Lecturer\TeachingWorkspaceService;
use Illuminate\Http\Request;

class LecturerTeachingController extends Controller
{
    public function __construct(private readonly TeachingWorkspaceService $workspace) {}

    public function index(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $lecturer = $request->user();
        $search = trim((string) $request->input('q', ''));
        $courses = $this->workspace->courseCards($lecturer)
            ->select(['courses.id', 'courses.uuid', 'courses.code', 'courses.name'])
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->orderBy('code')->orderBy('id')->simplePaginate(12)->withQueryString();
        $runtime = app(AiRuntimeConfigService::class);
        $aiAvailable = FeatureAccessService::canAccessFeature('ai_assistant', $lecturer)
            && $runtime->featureEnabled('lecturer_assistant', $lecturer->university_id)
            && $runtime->mode($lecturer->university_id) !== AiMode::DISABLED;
        $overview = $this->workspace->overview($lecturer, $aiAvailable);

        return view('lecturer.teaching-studio', compact('courses', 'overview', 'aiAvailable'));
    }
}
