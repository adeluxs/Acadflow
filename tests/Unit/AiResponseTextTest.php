<?php

namespace Tests\Unit;

use App\Support\AiResponseText;
use PHPUnit\Framework\TestCase;

class AiResponseTextTest extends TestCase
{
    public function test_it_flattens_a_structured_draft_json_into_readable_text(): void
    {
        $json = json_encode([
            'status' => 'draft',
            'draft_only' => true,
            'notice' => 'No course materials were found.',
            'lesson_plan' => [
                'topic' => 'Introduction to Computers',
                'learning_objectives' => ['Define a computer', 'Distinguish hardware and software'],
            ],
        ]);

        $text = AiResponseText::clean($json);

        $this->assertStringContainsString('Introduction to Computers', $text);
        $this->assertStringContainsString('Define a computer', $text);
        $this->assertStringNotContainsString('{', $text);
        $this->assertStringNotContainsString('"lesson_plan"', $text);
    }

    public function test_it_prefers_an_answer_field_inside_json(): void
    {
        $json = json_encode(['answer' => 'Plain human answer.', 'extra' => 'ignored']);

        $this->assertSame('Plain human answer.', AiResponseText::clean($json));
    }

    public function test_it_returns_null_for_non_json_text(): void
    {
        $this->assertNull(AiResponseText::humanizeJson('Just a normal sentence.'));
    }

    public function test_it_preserves_normal_prose_and_source_label_cleanup(): void
    {
        $text = AiResponseText::clean("## Heading\n- point one [S1]\n- point two");

        $this->assertStringContainsString('Heading', $text);
        $this->assertStringContainsString('point one', $text);
        $this->assertStringNotContainsString('[S1]', $text);
    }
}
