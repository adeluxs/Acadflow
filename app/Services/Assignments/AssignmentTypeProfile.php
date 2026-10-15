<?php

declare(strict_types=1);

namespace App\Services\Assignments;

use App\Services\SettingService;
use Illuminate\Validation\Rule;

/**
 * Single source of truth for the assignment types and the type-specific fields
 * each one requires at creation time. Keeping this in one place means the
 * controller, the Blade form and the tests all branch from the same definition,
 * and an assignment can never be created through an overly generic path.
 */
class AssignmentTypeProfile
{
    /**
     * @return array<string, array{label:string, description:string, fields:array<int,array<string,mixed>>}>
     */
    public static function definitions(): array
    {
        return [
            'assignment' => [
                'label' => 'Assignment',
                'description' => 'A standard coursework assignment.',
                'fields' => [],
            ],
            'group' => [
                'label' => 'Group Work',
                'description' => 'A collaborative submission completed by a group.',
                'fields' => [
                    ['key' => 'group_formation', 'label' => 'Group formation', 'type' => 'select', 'required' => true, 'options' => ['self_formed' => 'Students choose groups', 'assigned' => 'Lecturer assigns groups']],
                    ['key' => 'peer_evaluation', 'label' => 'Peer evaluation required', 'type' => 'checkbox', 'required' => false],
                    ['key' => 'presentation_date', 'label' => 'Presentation date', 'type' => 'date', 'required' => false],
                ],
            ],
            'project' => [
                'label' => 'Project',
                'description' => 'A supervised project with milestones and a defense.',
                'fields' => [
                    ['key' => 'project_title', 'label' => 'Project title', 'type' => 'text', 'required' => true],
                    ['key' => 'supervisor_name', 'label' => 'Supervisor', 'type' => 'text', 'required' => true],
                    ['key' => 'supervisor_email', 'label' => 'Supervisor email', 'type' => 'email', 'required' => false],
                    ['key' => 'proposal_deadline', 'label' => 'Proposal deadline', 'type' => 'date', 'required' => true],
                    ['key' => 'defense_required', 'label' => 'Defense required', 'type' => 'checkbox', 'required' => false],
                    ['key' => 'defense_date', 'label' => 'Defense date', 'type' => 'date', 'required' => false],
                    ['key' => 'milestones', 'label' => 'Milestones (one per line)', 'type' => 'textarea', 'required' => true],
                    ['key' => 'deliverables', 'label' => 'Deliverables', 'type' => 'textarea', 'required' => false],
                ],
            ],
            'siwes' => [
                'label' => 'SIWES',
                'description' => 'An industry placement with a logbook and evaluation.',
                'fields' => [
                    ['key' => 'placement_organisation', 'label' => 'Placement organisation', 'type' => 'text', 'required' => true],
                    ['key' => 'industry_supervisor', 'label' => 'Industry supervisor', 'type' => 'text', 'required' => true],
                    ['key' => 'industry_supervisor_contact', 'label' => 'Industry supervisor contact', 'type' => 'text', 'required' => false],
                    ['key' => 'placement_location', 'label' => 'Placement location', 'type' => 'text', 'required' => false],
                    ['key' => 'start_date', 'label' => 'Placement start', 'type' => 'date', 'required' => true],
                    ['key' => 'end_date', 'label' => 'Placement end', 'type' => 'date', 'required' => true],
                    ['key' => 'duration_weeks', 'label' => 'Duration (weeks)', 'type' => 'number', 'required' => true],
                    ['key' => 'logbook_expectation', 'label' => 'Logbook expectation', 'type' => 'select', 'required' => true, 'options' => ['weekly' => 'Weekly logbook', 'monthly' => 'Monthly logbook', 'none' => 'No logbook']],
                    ['key' => 'evaluation_method', 'label' => 'Evaluation method', 'type' => 'select', 'required' => true, 'options' => ['supervisor_report' => 'Industry supervisor report', 'presentation' => 'Student presentation', 'both' => 'Report and presentation']],
                ],
            ],
            'seminar' => [
                'label' => 'Seminar',
                'description' => 'A presented seminar with topic approval and a panel.',
                'fields' => [
                    ['key' => 'topic', 'label' => 'Seminar topic', 'type' => 'text', 'required' => true],
                    ['key' => 'topic_approval_required', 'label' => 'Topic approval required', 'type' => 'checkbox', 'required' => false],
                    ['key' => 'abstract', 'label' => 'Abstract', 'type' => 'textarea', 'required' => true],
                    ['key' => 'presentation_date', 'label' => 'Presentation date', 'type' => 'date', 'required' => true],
                    ['key' => 'panel_members', 'label' => 'Panel / assessors (one per line)', 'type' => 'textarea', 'required' => false],
                    ['key' => 'seminar_paper_required', 'label' => 'Seminar paper required', 'type' => 'checkbox', 'required' => false],
                    ['key' => 'slides_required', 'label' => 'Slides required', 'type' => 'checkbox', 'required' => false],
                ],
            ],
        ];
    }

    /** @return array<int,string> */
    public static function types(): array
    {
        return array_keys(self::definitions());
    }

    public static function supports(?string $type): bool
    {
        return $type !== null && array_key_exists($type, self::definitions());
    }

    /** @return array<int,array<string,mixed>> */
    public static function fields(?string $type): array
    {
        return self::definitions()[$type]['fields'] ?? [];
    }

    public static function label(?string $type): string
    {
        return self::definitions()[$type]['label'] ?? 'Assignment';
    }

    /**
     * Whether a type is currently enabled platform-wide. Optional types such as
     * SIWES are controlled by an administrator setting and default to disabled.
     */
    public static function isEnabled(?string $type): bool
    {
        if (! self::supports($type)) {
            return false;
        }

        return match ($type) {
            'siwes' => filter_var(SettingService::get('siwes_assignment_enabled', false), FILTER_VALIDATE_BOOLEAN),
            default => true,
        };
    }

    /** @return array<int,string> */
    public static function enabledTypes(): array
    {
        return array_values(array_filter(self::types(), fn (string $type): bool => self::isEnabled($type)));
    }

    /**
     * Definitions restricted to enabled types, for building forms and menus.
     *
     * @return array<string, array{label:string, description:string, fields:array<int,array<string,mixed>>}>
     */
    public static function availableDefinitions(): array
    {
        return array_filter(
            self::definitions(),
            fn (string $key): bool => self::isEnabled($key),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Validation rules for the `type_metadata` payload of a given type.
     *
     * @return array<string,mixed>
     */
    public static function rules(?string $type): array
    {
        $rules = [];

        foreach (self::fields($type) as $field) {
            $key = 'type_metadata.'.$field['key'];
            $base = match ($field['type']) {
                'checkbox' => ['boolean'],
                'number' => ['integer', 'min:1', 'max:520'],
                'email' => ['email', 'max:255'],
                'date' => ['date_format:Y-m-d'],
                'select' => [Rule::in(array_keys($field['options'] ?? []))],
                'textarea' => ['string', 'max:5000'],
                default => ['string', 'max:255'],
            };

            if ($field['required']) {
                array_unshift($base, 'required');
                // A required checkbox must be explicitly accepted when true is needed.
                if ($field['type'] === 'checkbox') {
                    $base = ['boolean'];
                }
            } else {
                array_unshift($base, 'nullable');
            }

            $rules[$key] = $base;
        }

        return $rules;
    }

    /**
     * Keep only the fields that belong to the chosen type and cast their values,
     * so arbitrary keys cannot be smuggled into the JSON document.
     *
     * @param  array<string,mixed>  $metadata
     * @return array<string,mixed>
     */
    public static function normalize(?string $type, array $metadata): array
    {
        $normalized = [];

        foreach (self::fields($type) as $field) {
            $key = $field['key'];
            if (! array_key_exists($key, $metadata)) {
                continue;
            }

            $value = $metadata[$key];

            $normalized[$key] = match ($field['type']) {
                'checkbox' => (bool) $value,
                'number' => (int) $value,
                default => is_string($value) ? trim($value) : $value,
            };
        }

        return $normalized;
    }
}
