<?php

namespace App\Enums;

use Illuminate\Support\Facades\Str;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case UNIVERSITY_ADMIN = 'university_admin';
    case DEPARTMENT_ADMIN = 'department_admin';
    case LECTURER = 'lecturer';
    case STUDENT = 'student';
    case MEMBER = 'member';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::UNIVERSITY_ADMIN => 'University Admin',
            self::DEPARTMENT_ADMIN => 'Department Admin',
            self::LECTURER => 'Lecturer',
            self::STUDENT => 'Student',
            self::MEMBER => 'Platform Member',
        };
    }
}