<?php

declare(strict_types=1);

namespace App\Services;

class PublicWebsite
{
    public const PATHS = [
        'features' => 'features', 'how-it-works' => 'how-it-works', 'about' => 'about',
        'contact' => 'contact', 'faq' => 'faqs', 'help' => 'help',
        'privacy' => 'privacy', 'terms' => 'terms', 'cookies' => 'cookies', 'disclaimer' => 'disclaimer',
    ];

    public static function supportEmail(): ?string
    {
        $email = trim((string) SettingService::getGlobal('support_email', ''));
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)
            || in_array(strtolower($email), ['support@uniflow.edu', 'support@example.com'], true)
            || preg_match('/(^|\.)(example\.(com|net|org)|test|invalid|localhost)$/i', $domain)) {
            return null;
        }

        return $email;
    }

    public static function features(): array
    {
        return [
            ['id' => 'learning', 'icon' => 'book', 'category' => 'Learning', 'title' => 'A clearer course experience', 'description' => 'Keep course materials, discussions, assignments and submission feedback in the same academic context.', 'use' => 'Find a lecture resource, ask a question and submit your assignment without losing the course thread.', 'details' => ['Course materials and enrollment', 'Assignments, deadlines and submission history', 'Lecturer feedback and attendance']],
            ['id' => 'research', 'icon' => 'research', 'category' => 'Research', 'title' => 'Research with a next step', 'description' => 'Move from a project idea to a structured, versioned manuscript in Research Studio.', 'use' => 'Write a chapter, collect references and work through supervisor corrections in one project.', 'details' => ['Chapter and section workspaces', 'Literature discovery and reference libraries', 'Review, corrections and approval workflows']],
            ['id' => 'publishing', 'icon' => 'layers', 'category' => 'Publishing', 'title' => 'Knowledge that travels further', 'description' => 'Publish and discover academic resources through Knowledge Hub, with visibility and moderation controls.', 'use' => 'Turn eligible research into a publication or organize resources into a reading list.', 'details' => ['Publications and creator profiles', 'Reading lists and learning paths', 'Publication-grounded AI Companion']],
            ['id' => 'collaboration', 'icon' => 'users', 'category' => 'Collaboration', 'title' => 'People alongside the work', 'description' => 'Bring academic conversations into course discussions, groups and communities.', 'use' => 'Discuss a topic with your course group or exchange ideas in an academic community.', 'details' => ['Course discussions and groups', 'Communities, events and challenges', 'Research collaborators and meeting tools where enabled']],
            ['id' => 'assistance', 'icon' => 'spark', 'category' => 'Assistance', 'title' => 'Assistance, with human judgment', 'description' => 'Use contextual academic assistants for learning, research and teaching when your administrator enables them.', 'use' => 'Explore a difficult concept or get help structuring a draft, then review the result yourself.', 'details' => ['Contextual academic AI tools', 'Study notes, practice and flashcards', 'Configurable providers and feature availability']],
            ['id' => 'institutions', 'icon' => 'building', 'category' => 'Institutions', 'title' => 'An institution, connected', 'description' => 'Coordinate faculties, departments, people and courses with role-based access and institution boundaries.', 'use' => 'Manage your academic structure while keeping student and staff access within the right scope.', 'details' => ['Institution and department administration', 'Role and permission controls', 'Billing, reporting and configurable modules']],
        ];
    }

    public static function workflows(): array
    {
        return [
            ['title' => 'Make it your workspace', 'body' => 'Create an account, verify your email when required, and complete the onboarding steps for your role and academic interests.', 'detail' => 'Students and lecturers can connect to their institution. Independent members can follow their own research and publishing path.'],
            ['title' => 'Start with the work in front of you', 'body' => 'Open a course, create a research project or explore Knowledge Hub. Your role and the modules enabled for your account shape what you see.', 'detail' => 'Use your dashboard as the starting point rather than keeping a separate list of disconnected tools.'],
            ['title' => 'Build, discuss and improve', 'body' => 'Work through course tasks, write project sections, collect references and invite the right feedback.', 'detail' => 'Versioned research sections, submission records and review steps keep the context attached to the work.'],
            ['title' => 'Take the next step', 'body' => 'Submit an assignment, address a correction or publish eligible knowledge. Keep learning with resources and communities relevant to you.', 'detail' => 'Approvals, visibility and institutional rules still apply. AI assistance supports people making these decisions.'],
        ];
    }

    public static function faqs(): array
    {
        return [
            ['topic' => 'Getting started', 'question' => 'Who can use AcadSyntra?', 'answer' => 'Students, lecturers, researchers, independent members and institution administrators use different parts of AcadSyntra. Onboarding helps connect your account to the relevant academic context; some roles and institution access require approval.'],
            ['topic' => 'Getting started', 'question' => 'Do I need to belong to an institution?', 'answer' => 'Independent members can use the research and knowledge workflows available to their account. Institution-specific courses, records and administration require the appropriate membership and permissions.'],
            ['topic' => 'Getting started', 'question' => 'What happens after I register?', 'answer' => 'You may be asked to verify your email, depending on the platform setting. Complete onboarding, choose your academic context and continue to your dashboard. An administrator may need to approve institutional access.'],
            ['topic' => 'Learning & research', 'question' => 'How do courses and Research Studio differ?', 'answer' => 'Courses organize learning resources, discussions, assignments, submissions and feedback. Research Studio organizes formal projects, manuscript sections, references and review workflows. The tools share your account while retaining their own access rules.'],
            ['topic' => 'Learning & research', 'question' => 'Can I keep drafts private?', 'answer' => 'Research projects and restricted course materials use permission and visibility rules. Publishing to Knowledge Hub is a separate action; confirm the selected audience and any institutional approval requirements before publishing.'],
            ['topic' => 'Learning & research', 'question' => 'Does AI make academic decisions?', 'answer' => 'AI tools provide assistance when enabled and configured. Their output can be incomplete or inaccurate and needs your review. Lecturers, supervisors and authorized reviewers remain responsible for grades, approvals and academic decisions.'],
            ['topic' => 'Accounts & access', 'question' => 'Are all features available to every account?', 'answer' => 'No. Your role, institution, permissions, commercial access and the administrator’s module settings determine availability. Features may be enabled, in maintenance or unreleased. Ask your institution administrator about access specific to your account.'],
            ['topic' => 'Accounts & access', 'question' => 'How much does access cost?', 'answer' => 'Pricing and commercial access are configured for this installation. Review the price and terms shown before any purchase, wallet funding or institution billing action. Contact the platform team for your institution’s requirements; this website does not promise a free plan.'],
            ['topic' => 'Accounts & access', 'question' => 'How do I reset my password?', 'answer' => 'Choose “Forgot password” on the sign-in page and enter your account email. Delivery depends on the installation’s email configuration. If you still cannot sign in, contact support without sharing your password or recovery codes.'],
            ['topic' => 'Privacy & support', 'question' => 'Can I use AcadSyntra on my phone?', 'answer' => 'The web application supports responsive layouts. Installation and offline features depend on whether the PWA module is enabled. Some workflows still require a connection; offline access is not a guarantee that every action will work without internet.'],
            ['topic' => 'Privacy & support', 'question' => 'How do browser notifications work?', 'answer' => 'Where push is enabled, sign in and open Notification Preferences to enable your browser. You choose whether to grant permission and can turn notifications off there or in browser settings. Email and in-app delivery have their own preferences.'],
            ['topic' => 'Privacy & support', 'question' => 'How can I contact the platform team?', 'answer' => 'Use the contact form for account questions, institution enquiries or privacy requests. Messages go to a restricted platform inbox. Do not include passwords, payment-card details or confidential student or research records.'],
        ];
    }

    public static function pages(): array
    {
        return array_replace(config('public_pages', []), config('public_website.pages', []));
    }
}
