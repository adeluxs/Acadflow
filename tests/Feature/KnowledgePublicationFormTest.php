<?php

namespace Tests\Feature;

use App\Models\KnowledgePublication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgePublicationFormTest extends TestCase
{
    use RefreshDatabase;

    /** Submits the create form the way a browser would: unticked checkboxes are not sent. */
    private function formData(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $form = $xpath->query('//form[@id="publication-main-form"]')->item(0);
        $this->assertNotNull($form);
        $data = [];
        foreach ($xpath->query('//*[@name]') as $element) {
            $inForm = $element->getAttribute('form') === 'publication-main-form';
            for ($parent = $element->parentNode; $parent && ! $inForm; $parent = $parent->parentNode) {
                $inForm = $parent === $form;
            }
            if (! $inForm || (in_array($element->getAttribute('type'), ['checkbox', 'radio'], true) && ! $element->hasAttribute('checked'))) {
                continue;
            }
            $data[$element->getAttribute('name')] = match ($element->nodeName) {
                'select' => ($xpath->query('.//option[@selected]', $element)->item(0) ?? $xpath->query('.//option', $element)->item(0))?->getAttribute('value') ?? '',
                'textarea' => $element->textContent,
                default => $element->getAttribute('value'),
            };
        }

        return [$form->getAttribute('action'), $data];
    }

    public function test_a_free_guide_can_be_created_without_the_selling_declaration(): void
    {
        $user = User::factory()->create();
        [$action, $data] = $this->formData($this->actingAs($user)->get(route('knowledge.manage.create'))->assertOk()->getContent());
        $this->assertArrayNotHasKey('ownership_declaration', $data);

        $this->post($action, ['title' => 'My first free guide', 'body' => '<p>Everything I learned in 100 level.</p>', 'access_type' => 'free'] + $data)
            ->assertSessionHasNoErrors();
        $this->assertTrue(KnowledgePublication::withoutGlobalScopes()->where('title', 'My first free guide')->exists());
    }

    public function test_selling_a_guide_still_needs_the_declaration(): void
    {
        $user = User::factory()->create();
        [$action, $data] = $this->formData($this->actingAs($user)->get(route('knowledge.manage.create'))->getContent());
        $base = ['title' => 'Premium guide', 'body' => '<p>Paid content.</p>', 'access_type' => 'premium', 'price' => '500'] + $data;

        $this->post($action, $base)->assertStatus(422);
        $this->post($action, ['ownership_declaration' => '0'] + $base)->assertSessionHasErrors('ownership_declaration');
        $this->assertFalse(KnowledgePublication::withoutGlobalScopes()->where('title', 'Premium guide')->exists());
    }
}
