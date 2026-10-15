<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StudentLearningProfile;
use Illuminate\Http\Request;

/**
 * Student learning preferences that personalize AI explanations.
 */
class LearningProfileController extends Controller
{
    public function show(Request $request)
    {
        $profile = StudentLearningProfile::firstOrCreate(['user_id' => $request->user()->id]);

        return view('student.learning-profile', [
            'profile' => $profile,
            'options' => [
                'pace' => ['slow' => 'Slow and thorough', 'steady' => 'Steady', 'fast' => 'Fast'],
                'level' => ['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'],
                'explanation_style' => ['simple' => 'Simple language', 'balanced' => 'Balanced', 'detailed' => 'Detailed and technical'],
                'reading_level' => ['simple' => 'Simple', 'standard' => 'Standard', 'advanced' => 'Advanced'],
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'pace' => ['nullable', 'in:slow,steady,fast'],
            'level' => ['nullable', 'in:beginner,intermediate,advanced'],
            'explanation_style' => ['nullable', 'in:simple,balanced,detailed'],
            'language' => ['nullable', 'string', 'max:10'],
            'reading_level' => ['nullable', 'in:simple,standard,advanced'],
            'goals' => ['nullable', 'string', 'max:2000'],
        ]);

        $profile = StudentLearningProfile::updateOrCreate(['user_id' => $request->user()->id], $data);

        AuditLog::log(
            'learning_profile_updated',
            $request->user()->id,
            StudentLearningProfile::class,
            $profile->id,
            null,
            $data,
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with('success', 'Learning preferences saved. New AI answers will follow them.');
    }
}
