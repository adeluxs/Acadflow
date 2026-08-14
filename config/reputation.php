<?php

return [
    'weights' => [
        'knowledge' => (float) env('REPUTATION_WEIGHT_KNOWLEDGE', 0.30),
        'quality' => (float) env('REPUTATION_WEIGHT_QUALITY', 0.30),
        'research_impact' => (float) env('REPUTATION_WEIGHT_RESEARCH', 0.30),
        'community' => (float) env('REPUTATION_WEIGHT_COMMUNITY', 0.10),
    ],
    'points' => [
        'publication' => (float) env('REPUTATION_POINTS_PUBLICATION', 12),
        'bookmark' => (float) env('REPUTATION_POINTS_BOOKMARK', 0.7),
        'view' => (float) env('REPUTATION_POINTS_VIEW', 0.02),
        'citation' => (float) env('REPUTATION_POINTS_CITATION', 5),
        'featured_publication' => (float) env('REPUTATION_POINTS_FEATURED_PUBLICATION', 8),
        'approved_research_quality' => (float) env('REPUTATION_POINTS_APPROVED_RESEARCH_QUALITY', 10),
        'citation_research_impact' => (float) env('REPUTATION_POINTS_CITATION_RESEARCH_IMPACT', 8),
        'approved_research_impact' => (float) env('REPUTATION_POINTS_APPROVED_RESEARCH_IMPACT', 15),
        'research_output' => (float) env('REPUTATION_POINTS_RESEARCH_OUTPUT', 6),
        'follower' => (float) env('REPUTATION_POINTS_FOLLOWER', 2),
        'comment' => (float) env('REPUTATION_POINTS_COMMENT', 0.8),
        'reaction' => (float) env('REPUTATION_POINTS_REACTION', 0.4),
    ],
    'levels' => [
        ['key' => 'hall_of_knowledge', 'name' => 'Hall of Knowledge', 'minimum' => (float) env('REPUTATION_LEVEL_HALL_OF_KNOWLEDGE', 2500)],
        ['key' => 'professor_circle', 'name' => 'Professor Circle', 'minimum' => (float) env('REPUTATION_LEVEL_PROFESSOR_CIRCLE', 1500)],
        ['key' => 'distinguished_researcher', 'name' => 'Distinguished Researcher', 'minimum' => (float) env('REPUTATION_LEVEL_DISTINGUISHED_RESEARCHER', 1000)],
        ['key' => 'research_fellow', 'name' => 'Research Fellow', 'minimum' => (float) env('REPUTATION_LEVEL_RESEARCH_FELLOW', 700)],
        ['key' => 'national_contributor', 'name' => 'National Contributor', 'minimum' => (float) env('REPUTATION_LEVEL_NATIONAL_CONTRIBUTOR', 450)],
        ['key' => 'university_scholar', 'name' => 'University Scholar', 'minimum' => (float) env('REPUTATION_LEVEL_UNIVERSITY_SCHOLAR', 250)],
        ['key' => 'faculty_expert', 'name' => 'Faculty Expert', 'minimum' => (float) env('REPUTATION_LEVEL_FACULTY_EXPERT', 120)],
        ['key' => 'department_expert', 'name' => 'Department Expert', 'minimum' => (float) env('REPUTATION_LEVEL_DEPARTMENT_EXPERT', 60)],
        ['key' => 'campus_contributor', 'name' => 'Campus Contributor', 'minimum' => (float) env('REPUTATION_LEVEL_CAMPUS_CONTRIBUTOR', 20)],
        ['key' => 'new_contributor', 'name' => 'New Contributor', 'minimum' => (float) env('REPUTATION_LEVEL_NEW_CONTRIBUTOR', 0)],
    ],
];
