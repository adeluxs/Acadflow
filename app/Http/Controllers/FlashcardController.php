<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\FlashcardDeck;
use App\Services\Ai\FlashcardService;
use Illuminate\Http\Request;

/**
 * Student spaced-repetition flashcards generated from authorized course context.
 */
class FlashcardController extends Controller
{
    public function __construct(private readonly FlashcardService $flashcards) {}

    public function index(Request $request)
    {
        $decks = FlashcardDeck::query()
            ->where('user_id', $request->user()->id)
            ->with('course:id,uuid,code,name')
            ->latest('id')
            ->paginate(20);

        return view('student.flashcards', [
            'decks' => $decks,
            'courses' => $this->enrolledCourses($request),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'material_id' => ['nullable', 'integer', 'exists:course_materials,id'],
            'topic' => ['required', 'string', 'min:2', 'max:200'],
            'count' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $user = $request->user();
        $course = Course::with('department.faculty')->findOrFail($data['course_id']);
        abort_unless($user->canAccessCourse($course), 403, 'You do not have access to that course.');

        $material = null;
        if (! empty($data['material_id'])) {
            $candidate = CourseMaterial::find($data['material_id']);
            if ($candidate && (int) $candidate->course_id === (int) $course->id) {
                $material = $candidate;
            }
        }

        $deck = $this->flashcards->generate($user, $course, $data['topic'], $material, (int) ($data['count'] ?? 10));

        return redirect()->route('flashcards.show', $deck)->with('success', 'Flashcard deck ready.');
    }

    public function show(Request $request, FlashcardDeck $deck)
    {
        abort_unless($deck->user_id === $request->user()->id, 404);

        $deck->load('course:id,uuid,code,name');
        $cards = is_array($deck->cards) ? $deck->cards : [];

        return view('student.flashcard-deck', compact('deck', 'cards'));
    }

    public function review(Request $request, FlashcardDeck $deck)
    {
        abort_unless($deck->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'card_index' => ['required', 'integer', 'min:0'],
            'rating' => ['required', 'integer', 'min:0', 'max:5'],
        ]);

        $card = $this->flashcards->review($deck, $request->user(), (int) $data['card_index'], (int) $data['rating']);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'card' => $card]);
        }

        return back();
    }

    private function enrolledCourses(Request $request)
    {
        return Course::query()
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', $request->user()->id)->where('status', 'enrolled'))
            ->orderBy('code')
            ->get(['id', 'uuid', 'code', 'name']);
    }
}
