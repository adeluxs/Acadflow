<?php

namespace App\Ai\Rules;

class KnowledgePublicationRulePack extends BaseRulePack
{
    public function key(): string { return 'knowledge_publication'; }
    public function label(): string { return 'Knowledge Hub Publication'; }

    public function analyze(array $context): array
    {
        $text = trim(strip_tags((string) ($context['text'] ?? $context['content'] ?? '')));
        $issues = [];
        if (mb_strlen($text) < 500) {
            $issues[] = $this->issue('publication_too_short', 'The publication is too short for a substantive academic resource.', 'warning', 3, 'Expand the publication with evidence, explanation, and references.', 'publication_quality');
        }
        if (empty($context['title'])) {
            $issues[] = $this->issue('publication_title_missing', 'A publication title is required.', 'critical', 1, 'Add a precise and descriptive title.', 'metadata');
        }
        if (empty($context['references']) && ! preg_match('/\breferences?\b/i', $text)) {
            $issues[] = $this->issue('publication_references_missing', 'No references were identified for this academic publication.', 'warning', 2, 'Add and verify the sources supporting the publication.', 'references');
        }
        if (($context['access_type'] ?? 'free') === 'premium' && empty($context['price'])) {
            $issues[] = $this->issue('premium_price_missing', 'Premium content has no valid price.', 'critical', 1, 'Set a valid price or change access to free/institution.', 'commerce');
        }

        return $this->result($issues, ['moderation_recommended' => $issues !== []]);
    }
}
