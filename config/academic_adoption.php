<?php

return [
    // These are adoption arrangements, not a second recurring subscription engine.
    // Platform Admin approves commercial terms through the existing account flow.
    'plans' => [
        'department_core' => ['name' => 'Department Core', 'scope' => 'department', 'description' => 'Department administration and core academic work. Paid features use existing pricing.'],
        'department_sponsored' => ['name' => 'Department Sponsored', 'scope' => 'department', 'description' => 'Request approved AI or paid-feature sponsorship for verified members.'],
        'institution_core' => ['name' => 'Institution Core', 'scope' => 'institution', 'description' => 'Institution administration and affiliated departments.'],
        'institution_sponsored' => ['name' => 'Institution Sponsored', 'scope' => 'institution', 'description' => 'Approved institution-wide commercial sponsorship.'],
    ],
    'classifications' => ['undergraduate', 'postgraduate', 'mixed', 'polytechnic', 'college', 'professional', 'research'],
    'invitation_days' => 7,
];
