<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\PracticeQuiz;
use App\Services\Ai\PracticeQuizService;
use Illuminate\Http\Request;

/**
 * Student practice quizzes. Quizzes are student-owned study aids generated from
 * authorized course context.
 */
class PracticeQuizController extends Controller
{
    public function __construct(private readonly PracticeQuizService $quizzes) {}

    public function index(Request $request)
    {
        $quizzes = PracticeQuiz::query()
            ->where('user_id', $request->user()->id)
            ->with('course:id,uuid,code,name')
            ->latest('id')
            ->paginate(20);

        return view('student.practice-quizzes', [
            'quizzes' => $quizzes,
            'courses' => $this->enrolledCourses($request),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'material_id' => ['nullable', 'integer', 'exists:course_materials,id'],
            'topic' => ['required', 'string', 'min:2', 'max:200'],
            'count' => ['nullable', 'integer', 'min:1', 'max:15'],
            'difficulty' => ['nullable', 'in:easy,medium,hard,mixed'],
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

        $quiz = $this->quizzes->generate(
            $user,
            $course,
            $data['topic'],
            $material,
            (int) ($data['count'] ?? 5),
            $data['difficulty'] ?? 'mixed',
        );

        return redirect()->route('practice-quizzes.show', $quiz)->with('success', 'Practice quiz ready.');
    }

    public function show(Request $request, PracticeQuiz $quiz)
    {
        abort_unless($quiz->user_id === $request->user()->id, 404);

        $quiz->load('course:id,uuid,code,name', 'material:id,title');

        $attempts = $quiz->attempts()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->take(5)
            ->get();

        return view('student.practice-quiz', compact('quiz', 'attempts'));
    }

    public function submit(Request $request, PracticeQuiz $quiz)
    {
        abort_unless($quiz->user_id === $request->user()->id, 404);

        $attempt = $this->quizzes->gradeAttempt($quiz, $request->user(), (array) $request->input('answers', []));

        return redirect()->route('practice-quizzes.show', $quiz)
            ->with('success', 'You scored '.$attempt->score.'/'.$attempt->total.'.');
    }

    private function enrolledCourses(Request $request)
    {
        return Course::query()
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', $request->user()->id)->where('status', 'enrolled'))
            ->orderBy('code')
            ->get(['id', 'uuid', 'code', 'name']);
    }
}
