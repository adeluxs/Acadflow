<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PublicPageController extends Controller
{
    public function show(string $page): View
    {
        $pages = [
            'help' => [
                'title' => 'Help Center',
                'intro' => 'Guidance for joining AcadFlow, publishing knowledge, collaborating, and managing an institution.',
                'sections' => [
                    ['Getting started', 'Create an account, verify your email, complete the onboarding path that matches your role, and use the dashboard recommendations to find relevant research, communities, events, and challenges.'],
                    ['Account support', 'Use password recovery from the login page. After signing in, notification and privacy preferences can be changed from your personal settings.'],
                    ['Reporting problems', 'Use the report action on communities, groups, events, challenges, publications, and discussions. Reports are retained for authorized human moderation.'],
                ],
            ],
            'documentation' => [
                'title' => 'Product Documentation',
                'intro' => 'A practical overview of AcadFlow’s integrated academic ecosystem.',
                'sections' => [
                    ['Research Studio', 'Configure a research type and workflow, create a project, write versioned sections, manage references, collaborate with supervisors, use the Research/SIWES/Project AI assistants where enabled, validate, approve, archive, and publish eligible work.'],
                    ['Knowledge Hub', 'Create and moderate publications, use the publication-grounded AI Companion, build creator reputation, organize learning paths and reading lists, join communities, attend events, and participate in academic challenges.'],
                    ['Institutions', 'University and department administrators manage academic structures, users, courses, billing, settings, reports, and tenant-aware AI controls. Super administrators also manage centralized feature availability and platform AI routing.'],
                ],
            ],
            'status' => [
                'title' => 'Platform Status',
                'intro' => 'This installation reports application health from its own configured environment.',
                'sections' => [
                    ['Application', 'The web application is responding. Administrators should use queue, scheduler, mail, database, storage, search, and AI-provider health checks in their deployment monitoring.'],
                    ['Background processing', 'Production deployments must run queue workers and the Laravel scheduler. Failed jobs and notification retries are available to authorized administrators.'],
                    ['Incident reporting', 'Operational incidents should be recorded in the organization’s support process without exposing credentials, stack traces, or private academic records.'],
                ],
            ],
            'about' => [
                'title' => 'About AcadFlow',
                'intro' => 'AcadFlow connects academic work, people, institutions, and trusted knowledge in one platform.',
                'sections' => [
                    ['Mission', 'Help students, lecturers, researchers, institutions, independent professionals, authors, and publishers learn, collaborate, publish, build reputation, and share verified knowledge.'],
                    ['Product boundaries', 'Research Studio owns formal supervised research. Knowledge Hub owns public and institution-visible publishing, discovery, communities, events, learning, and monetization. Shared platform services prevent duplicate systems.'],
                    ['Human authority', 'AI provides structured assistance and evidence. Authorized people remain responsible for grading, supervision, approval, verification, moderation, sanctions, and final decisions.'],
                ],
            ],
            'careers' => [
                'title' => 'Careers and Partnerships',
                'intro' => 'AcadFlow supports institutions and professional partners building better academic infrastructure.',
                'sections' => [
                    ['Opportunities', 'Published openings and partnership opportunities should be announced through verified institution or organization profiles and moderated Knowledge Hub publications.'],
                    ['Contributors', 'Academic experts can publish resources, create learning paths, mentor communities, organize events, judge challenges, and collaborate on research subject to platform permissions.'],
                    ['Contact', 'Use the contact channel configured by the administrator for employment, partnership, or procurement enquiries.'],
                ],
            ],
            'api' => [
                'title' => 'API Overview',
                'intro' => 'AcadFlow exposes authenticated APIs using the project’s versioned routes and authorization rules.',
                'sections' => [
                    ['Authentication', 'API access uses Laravel Sanctum. Tokens must be scoped to a real user, stored securely, rotated when compromised, and never embedded in public clients.'],
                    ['Tenant boundaries', 'Every API consumer must preserve institution, department, role, visibility, and entitlement boundaries. A successful request never bypasses policy checks.'],
                    ['Integration guidance', 'Use the route list and generated API documentation from the deployed source as the contract. Long-running AI, indexing, export, and validation operations return job or status resources.'],
                ],
            ],
            'source' => [
                'title' => 'Source and Contributions',
                'intro' => 'Source repository information is controlled by the owner of this AcadFlow installation.',
                'sections' => [
                    ['Repository', 'Administrators can publish the approved repository URL through centralized settings. No repository link is exposed until one is explicitly configured.'],
                    ['Security reports', 'Do not publish exploitable vulnerabilities. Use the private security contact configured by the platform operator.'],
                    ['Contribution process', 'Changes should include migrations, policies, tests, documentation, and regression evidence, and should preserve existing route and data contracts.'],
                ],
            ],
            'changelog' => [
                'title' => 'Changelog',
                'intro' => 'A high-level record of platform capability areas. Deployment-specific release notes belong in the source repository.',
                'sections' => [
                    ['Centralized AI', 'AcadFlow now routes the main AI Assistant, Grounded Companion, Research, Assignment, SIWES, Project, Material and Discussion assistants, validators and moderation through one provider-routing architecture with explicit Provider AI, Hybrid, Rule-Based and Disabled modes.'],
                    ['Feature management', 'Thirty platform modules can be enabled, placed in maintenance, or kept unreleased from one central Feature & Module Management area with dependency-aware access control.'],
                    ['Academic ecosystem', 'Research Studio, Knowledge Hub, courses, assignments/submissions, attendance, groups, communities, events, challenges, learning paths, commerce, notifications and reporting share tenant-aware identity, permissions and audit foundations.'],
                ],
            ],
            'security' => [
                'title' => 'Security',
                'intro' => 'AcadFlow protects academic records through layered authorization, tenant isolation, secure media handling, and auditable human decisions.',
                'sections' => [
                    ['Access control', 'Policies, role middleware, verified email, completed onboarding, session checks, rate limits, and tenant-aware queries protect restricted actions.'],
                    ['Files and downloads', 'Uploads are validated and scanned before storage. Restricted resources use authorization checks, expiring tokens, access logs, and safe content-disposition headers.'],
                    ['Responsible disclosure', 'Report vulnerabilities privately to the deployment owner. Never include live credentials, private research data, or personal information in a public report.'],
                ],
            ],
            'terms' => [
                'title' => 'Terms of Use',
                'intro' => 'Use AcadFlow lawfully, respectfully, and in accordance with applicable institutional policies.',
                'sections' => [
                    ['Acceptable use', 'Do not impersonate others, bypass access controls, distribute malware, harass users, manipulate reputation or voting, or misuse protected academic and assessment content.'],
                    ['Academic integrity', 'Similarity indicators are not automatic proof of misconduct. Users remain responsible for attribution, permissions, originality, and compliance with institutional rules.'],
                    ['Account responsibility', 'Keep credentials and recovery codes secure. Platform operators may suspend access where necessary to protect users, institutions, or data, subject to documented review.'],
                ],
            ],
            'privacy' => [
                'title' => 'Privacy Policy',
                'intro' => 'AcadFlow separates public profiles and publications from private academic, institutional, and commercial records.',
                'sections' => [
                    ['Data controls', 'Users choose profile visibility and notification preferences. Institutions control access to tenant-scoped academic records under their applicable policies.'],
                    ['Processing', 'The platform records activity needed for authentication, workflows, security, analytics, billing, AI usage, moderation, and audit. Access is limited by authorization.'],
                    ['AI and documents', 'AI requests must use authorized content and tenant-aware settings. Uploaded documents are treated as untrusted input and must not override system or institutional rules.'],
                ],
            ],
            'cookies' => [
                'title' => 'Cookie Policy',
                'intro' => 'AcadFlow uses cookies and comparable browser storage for secure sessions and essential application behavior.',
                'sections' => [
                    ['Essential storage', 'Session, CSRF, authentication, preference, and security state are required for signed-in features.'],
                    ['Analytics', 'Optional analytics should be enabled only in accordance with the deployment owner’s privacy settings and applicable law.'],
                    ['Control', 'Browser controls can remove stored data, but removing essential session data signs the user out and may reset local preferences.'],
                ],
            ],
            'licenses' => [
                'title' => 'Licenses and Attributions',
                'intro' => 'This installation combines application source, framework packages, and optional provider integrations under their respective licenses.',
                'sections' => [
                    ['Application license', 'The repository owner defines the license and distribution terms for this AcadFlow installation.'],
                    ['Dependencies', 'Composer and npm lock files are the authoritative dependency inventories after installation. Production builds must retain required notices and comply with package licenses.'],
                    ['User content', 'Creators retain or grant rights according to platform and institutional terms. Publishing content does not remove third-party copyright or access restrictions.'],
                ],
            ],
        ];

        abort_unless(isset($pages[$page]), 404);

        return view('public.page', ['page' => $pages[$page], 'slug' => $page]);
    }
}
