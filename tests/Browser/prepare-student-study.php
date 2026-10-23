<?php

declare(strict_types=1);
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\PracticeQuiz;
use App\Models\Semester;
use App\Models\StudentStudySession;
use App\Models\StudyNote;
use App\Models\University;
use App\Models\User;
use App\Services\SettingService;
use App\Services\Student\StudySourceService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = config('database.connections.sqlite.database');
if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_starts_with((string) $database, '/private/tmp/acadsyntra-studio-study-')) {
    throw new RuntimeException('Use an isolated /private/tmp/acadsyntra-studio-study-* testing database.');
}
Artisan::call('migrate', ['--force' => true]);
SettingService::set('email_verification_enabled', false, 'boolean');
SettingService::set('allowed_file_extensions', 'pdf,docx,txt', 'string');
$student = User::where('email', 'study.student@acadsyntra.test')->first();
if (! $student) {
    $university = University::factory()->create();
    $faculty = Faculty::factory()->create(['university_id' => $university->id]);
    $department = Department::factory()->create(['faculty_id' => $faculty->id]);
    $academicSession = AcademicSession::factory()->create(['university_id' => $university->id, 'is_current' => true, 'is_active' => true]);
    $semester = Semester::factory()->create(['academic_session_id' => $academicSession->id, 'is_active' => true]);
    $course = Course::factory()->create(['department_id' => $department->id, 'code' => 'CSC301', 'name' => 'Database systems']);
    $student = User::factory()->create(['email' => 'study.student@acadsyntra.test', 'first_name' => 'Study', 'last_name' => 'Student', 'role' => 'student', 'account_type' => 'student', 'university_id' => $university->id, 'department_id' => $department->id, 'is_active' => true, 'email_verified_at' => now(), 'onboarding_completed_at' => now()]);
    Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'semester_id' => $semester->id, 'status' => 'enrolled', 'enrolled_at' => now()]);
    $material = CourseMaterial::create(['course_id' => $course->id, 'semester_id' => $semester->id, 'uploaded_by' => $student->id, 'title' => 'Normalization essentials', 'type' => 'lecture_note', 'content' => '<p>First normal form removes repeating groups.</p><p>Every field holds one atomic value.</p>', 'is_visible' => true]);
    $session = StudentStudySession::create(['user_id' => $student->id, 'course_id' => $course->id, 'material_id' => $material->id, 'last_studied_at' => now(), 'selected_text' => 'Every field holds one atomic value.', 'question' => 'Explain atomic values simply', 'explanation' => 'An atomic value is a single value in one field. For example, put one phone number in each phone-number row. Self-check: how would you split a field containing two phone numbers?', 'explained_at' => now(), 'source_fingerprint' => hash('sha256', app(StudySourceService::class)->plain($material->content))]);
    PracticeQuiz::create(['user_id' => $student->id, 'course_id' => $course->id, 'material_id' => $material->id, 'study_session_id' => $session->id, 'title' => 'Practice: Atomic values', 'topic' => 'Atomic values', 'status' => 'ready', 'next_review_at' => now()->subDay(), 'questions' => [
        ['question' => 'What does first normal form require?', 'options' => ['Atomic values', 'Repeated columns'], 'answer' => 'Atomic values', 'explanation' => 'Each field holds one value.', 'source_excerpt' => 'Every field holds one atomic value.', 'difficulty' => 'easy'],
        ['question' => 'What does first normal form remove?', 'options' => ['Primary keys', 'Repeating groups'], 'answer' => 'Repeating groups', 'explanation' => 'Repeated values move to individual rows.', 'difficulty' => 'easy'],
    ]]);
}
$course = Course::whereHas('enrollments', fn ($q) => $q->where('user_id', $student->id))->firstOrFail();
$session = StudentStudySession::where('user_id', $student->id)->whereNotNull('material_id')->firstOrFail();
$quiz = PracticeQuiz::where('study_session_id', $session->id)->firstOrFail();
$history = max(0, min(100, (int) (getopt('', ['history:'])['history'] ?? 0)));
for ($i = 0; $i < $history; $i++) {
    $note = StudyNote::firstOrCreate(['user_id' => $student->id, 'course_id' => $course->id, 'title' => 'History '.$i], ['content' => str_repeat('Large private course source. ', 1000), 'source' => 'manual']);
    $saved = StudentStudySession::firstOrCreate(['user_id' => $student->id, 'note_id' => $note->id], ['course_id' => $course->id, 'last_studied_at' => now(), 'explanation' => str_repeat('Saved explanation. ', 1000)]);
    PracticeQuiz::firstOrCreate(['user_id' => $student->id, 'study_session_id' => $saved->id], ['course_id' => $course->id, 'title' => 'History practice '.$i, 'topic' => 'History '.$i, 'status' => 'ready', 'questions' => $quiz->questions, 'next_review_at' => now()->subDay()]);
}
$sessionId = Str::random(40);
DB::table('sessions')->where('user_id', $student->id)->delete();
$quiz->attempts()->where('user_id', $student->id)->delete();
$quiz->update(['last_practiced_at' => null, 'review_interval_days' => 0, 'next_review_at' => now()->subDay()]);
DB::table('sessions')->insert(['id' => $sessionId, 'user_id' => $student->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Student study browser fixture', 'payload' => base64_encode(serialize(['_token' => Str::random(40), auth()->guard('web')->getName() => $student->id])), 'last_activity' => time()]);
$cookieName = config('session.cookie');
$encrypter = app('encrypter');
$fixture = ['user_id' => $student->id, 'course_id' => $course->id, 'workspace' => '/study/sessions/'.$session->uuid, 'quiz' => '/practice-quizzes/'.$quiz->uuid, 'cookie' => ['name' => $cookieName, 'value' => $encrypter->encrypt(CookieValuePrefix::create($cookieName, $encrypter->getKey()).$sessionId, false), 'domain' => '127.0.0.1', 'path' => '/', 'httpOnly' => true, 'secure' => false, 'sameSite' => 'Lax']];
$path = '/private/tmp/acadsyntra-studio-study-fixture.json';
file_put_contents($path, json_encode($fixture, JSON_THROW_ON_ERROR));
chmod($path, 0600);
echo "Isolated student study fixture ready; user {$student->id}, history {$history}.\n";
