<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Runtime-paid feature registry
    |--------------------------------------------------------------------------
    |
    | `fixed_access` creates a time-bound/non-expiring entitlement after one
    | successful purchase. `pay_per_use` reserves and settles funds around the
    | protected operation. AI targets are listed separately below and always use
    | `ai_usage` pricing.
    |
    */
    'pricing_features' => [
        'student_study_tools' => [
            'title' => 'Student Study Tools',
            'short_title' => 'Study Pro',
            'category' => 'Learning & Revision',
            'description' => 'Unlocks My Study Space, private study notes, practice quizzes, flashcards, study plans, mastery, deadline coaching, self-assessment, annotations and reflections. Included AI requests are used before any AI overage price.',
            'pricing_type' => 'fixed_access',
            'unit_label' => 'access period',
            'roles' => ['student'],
            'package' => true,
            'package_kind' => 'workspace',
            'default_duration_days' => 30,
            'default_semester_duration_days' => 120,
            'default_trial_days' => 0,
            'default_grace_days' => 7,
            'ai_features' => ['study_assistant', 'material_assistant', 'practice_generator', 'flashcard_generator', 'study_planner', 'annotation_summary'],
        ],
        'lecturer_teaching_tools' => [
            'title' => 'Lecturer Teaching Studio',
            'short_title' => 'Teaching Pro',
            'category' => 'Courses & Teaching',
            'description' => 'Unlocks Teaching Studio priorities, course pulse, student-question signals and the lecturer content-draft workflow. Included AI requests are used before any Lecturer Assistant overage price.',
            'pricing_type' => 'fixed_access',
            'unit_label' => 'access period',
            'roles' => ['lecturer'],
            'package' => true,
            'package_kind' => 'workspace',
            'default_duration_days' => 30,
            'default_semester_duration_days' => 120,
            'default_trial_days' => 0,
            'default_grace_days' => 7,
            'ai_features' => ['lecturer_assistant'],
        ],
        'academic_ai_plus' => [
            'title' => 'Academic AI Plus',
            'short_title' => 'AI Plus',
            'category' => 'Cross-platform AI',
            'description' => 'A shared allowance for specialist writing, citation, research, assessment, project, discussion and Knowledge Hub AI tools. Available to students, lecturers and other members alongside their role-specific package. Tool access still follows each workspace and content permission.',
            'pricing_type' => 'fixed_access',
            'unit_label' => 'access period',
            'roles' => ['student', 'lecturer', 'member'],
            'package' => true,
            'package_kind' => 'ai_addon',
            'catalogue_requires_price' => true,
            'requires_ai_monetization' => true,
            'default_duration_days' => 30,
            'default_semester_duration_days' => 120,
            'default_trial_days' => 0,
            'default_grace_days' => 0,
            'ai_features' => [
                'writing_assistant',
                'citation_assistant',
                'research_assistant',
                'research_validator',
                'assignment_assistant',
                'submission_validator',
                'plagiarism',
                'siwes_assistant',
                'project_assistant',
                'discussion_assistant',
                'knowledge_publication_validator',
                'knowledge_moderation',
                'knowledge_companion',
            ],
        ],
        'allow_group_submissions' => [
            'title' => 'Group Submissions',
            'category' => 'Assignments & Assessment',
            'description' => 'Allows lecturers to create assignments that students can complete and submit as a group.',
            'pricing_type' => 'pay_per_use',
            'unit_label' => 'assignment created',
        ],
        'allow_rubrics' => [
            'title' => 'Rubric-Based Grading',
            'category' => 'Assignments & Assessment',
            'description' => 'Allows lecturers to attach a grading rubric to an assignment and use rubric-based assessment workflows.',
            'pricing_type' => 'pay_per_use',
            'unit_label' => 'rubric-enabled assignment',
        ],
        'attendance_tracking' => [
            'title' => 'Attendance Tracking',
            'category' => 'Courses & Teaching',
            'description' => 'Allows authorized lecturers to start attendance-tracking sessions.',
            'pricing_type' => 'pay_per_use',
            'unit_label' => 'attendance session',
        ],
        'document_generation' => [
            'title' => 'Document & PDF Generation',
            'category' => 'Documents & Exports',
            'description' => 'Covers transcript, grade-report, course-material and supported batch PDF generation actions.',
            'pricing_type' => 'pay_per_use',
            'unit_label' => 'generation action',
        ],
        'knowledge_hub_premium' => [
            'title' => 'Knowledge Hub Premium Access',
            'category' => 'Knowledge Hub',
            'description' => 'Unlocks premium Knowledge Hub discovery/content protected by AcadSyntra commercial entitlements.',
            'pricing_type' => 'fixed_access',
            'default_duration_days' => 30,
            'unit_label' => 'access period',
        ],
    ],

    'ai_features' => [
        'study_assistant' => ['title' => 'Student / Study Assistant', 'category' => 'AI Assistants'],
        'lecturer_assistant' => ['title' => 'Lecturer Assistant', 'category' => 'AI Assistants'],
        'practice_generator' => ['title' => 'Practice Quiz Generator', 'category' => 'Student Study Tools'],
        'flashcard_generator' => ['title' => 'Flashcard Generator', 'category' => 'Student Study Tools'],
        'study_planner' => ['title' => 'Study & Deadline Planner', 'category' => 'Student Study Tools'],
        'annotation_summary' => ['title' => 'Highlight Summary', 'category' => 'Student Study Tools'],
        'writing_assistant' => ['title' => 'Writing Assistant', 'category' => 'AI Assistants'],
        'citation_assistant' => ['title' => 'Citation Assistant', 'category' => 'AI Assistants'],
        'research_assistant' => ['title' => 'Research Assistant', 'category' => 'Research Studio'],
        'research_validator' => ['title' => 'Research Validator', 'category' => 'Research Studio'],
        'assignment_assistant' => ['title' => 'Assignment Assistant', 'category' => 'Assignments & Assessment'],
        'submission_validator' => ['title' => 'Submission Validator', 'category' => 'Assignments & Assessment'],
        'plagiarism' => ['title' => 'Academic Integrity / Plagiarism AI', 'category' => 'Assignments & Assessment'],
        'siwes_assistant' => ['title' => 'SIWES Assistant', 'category' => 'Academic Workflows'],
        'project_assistant' => ['title' => 'Project Assistant', 'category' => 'Academic Workflows'],
        'material_assistant' => ['title' => 'Material / Study Assistant', 'category' => 'Courses & Teaching'],
        'discussion_assistant' => ['title' => 'Discussion Assistant', 'category' => 'Courses & Teaching'],
        'knowledge_publication_validator' => ['title' => 'Publication Validator', 'category' => 'Knowledge Hub'],
        'knowledge_moderation' => ['title' => 'Knowledge Moderator', 'category' => 'Knowledge Hub'],
        'knowledge_companion' => ['title' => 'Grounded AI Companion', 'category' => 'Knowledge Hub'],
    ],

    'pricing_types' => [
        'fixed_access' => 'Fixed access',
        'pay_per_use' => 'Pay per use',
        'ai_usage' => 'AI usage',
    ],
];
