<?php

namespace Tests\Feature\Ai;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiContextRoutesSecurityTest extends TestCase
{
    /** @return array<string,array{0:string,1:string}> */
    public static function contextualRoutes(): array
    {
        return [
            'research assistant' => ['ai.context.research', 'research_assistant'],
            'assignment assistant' => ['ai.context.assignment', 'assignment_assistant'],
            'siwes assistant' => ['ai.context.siwes', 'siwes_assistant'],
            'project assistant' => ['ai.context.project', 'project_assistant'],
            'material assistant' => ['ai.context.material', 'material_assistant'],
            'discussion assistant' => ['ai.context.discussion', 'discussion_assistant'],
        ];
    }

    #[DataProvider('contextualRoutes')]
    public function test_contextual_ai_routes_have_central_ai_feature_gate(string $routeName, string $feature): void
    {
        $route = app('router')->getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "Missing route {$routeName}");
        $middleware = $route->gatherMiddleware();

        $this->assertContains('feature.flag:ai_assistant', $middleware);
        $this->assertContains('ai.feature:'.$feature, $middleware);
        $this->assertContains('throttle:ai', $middleware);
    }
}
