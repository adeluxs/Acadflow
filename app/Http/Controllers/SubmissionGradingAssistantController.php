<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Services\Ai\SubmissionGradingAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Advisory grading assistance endpoint.
 *
 * Returns a suggested score and feedback draft for an authorized grader. It
 * does not persist a grade; the lecturer applies the suggestion through the
 * normal grading action.
 */
class SubmissionGradingAssistantController extends Controller
{
    public function __construct(
        private readonly SubmissionGradingAssistantService $assistant,
    ) {}

    public function suggest(Request $request, Submission $submission): JsonResponse
    {
        $this->authorize('grade', $submission);

        return response()->json($this->assistant->suggest($request->user(), $submission));
    }
}
