<?php

namespace App\Services;

use App\Models\ResearchProject;
use DOMDocument;
use DOMElement;
use DOMNode;

/** Writes a genuine Office Open XML package; accepts only sanitised stored content. */
class ResearchDocumentExportService
{
    public function docx(ResearchProject $project): string
    {
        abort_unless(class_exists(\ZipArchive::class) && class_exists(DOMDocument::class), 503, 'DOCX export is unavailable on this server. Contact your administrator.');
        $project->load(['sections.document', 'owner', 'referenceLinks.reference']);
        $body = $this->paragraph($project->title, 'Title').$this->paragraph($project->owner?->full_name ?? '');
        $body .= $this->paragraph('Contents', 'Heading1');
        $body .= '<w:p><w:fldSimple w:instr="TOC \o &quot;1-3&quot; \\h \\z \\u"><w:r><w:t>Update this table of contents in your word processor.</w:t></w:r></w:fldSimple></w:p>';
        foreach ($project->sections as $section) {
            $body .= $this->paragraph($section->title, 'Heading1');
            $html = app(RichTextSanitizer::class)->sanitize((string) $section->document?->body);
            if ($section->key === 'references' && trim(strip_tags($html)) === '') {
                foreach ($this->bibliography($project) as $reference) $body .= $this->paragraph($reference);
            } else {
                $dom = new DOMDocument;
                $previous = libxml_use_internal_errors(true);
                try { $dom->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
                finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
                foreach ($dom->getElementsByTagName('body')->item(0)?->childNodes ?? [] as $node) $body .= $this->block($node);
            }
        }
        $margin = max(15, min(40, (int) data_get($project->metadata, 'research_setup.format_margin', 25))) * 56.6929;
        $body .= '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="'.(int) $margin.'" w:right="'.(int) $margin.'" w:bottom="'.(int) $margin.'" w:left="'.(int) $margin.'"/></w:sectPr>';
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>';
        $font = data_get($project->metadata, 'research_setup.format_font') === 'sans-serif' ? 'Arial' : 'Times New Roman';
        $size = max(10, min(14, (int) data_get($project->metadata, 'research_setup.format_size', 12))) * 2;
        $spacing = (int) (max(1, min(2, (float) data_get($project->metadata, 'research_setup.format_spacing', 1.5))) * 240);
        $styles = '<?xml version="1.0" encoding="UTF-8"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="'.$font.'" w:hAnsi="'.$font.'"/><w:sz w:val="'.$size.'"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:line="'.$spacing.'" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>';
        foreach (['Title' => 36, 'Heading1' => 30, 'Heading2' => 27, 'Heading3' => 24] as $name => $pt) $styles .= '<w:style w:type="paragraph" w:styleId="'.$name.'"><w:name w:val="'.$name.'"/><w:pPr><w:keepNext/>'.($name === 'Title' ? '' : '<w:outlineLvl w:val="'.((int) substr($name, -1) - 1).'"/>').'</w:pPr><w:rPr><w:b/><w:sz w:val="'.$pt.'"/></w:rPr></w:style>';
        $styles .= '</w:styles>';
        $path = tempnam(sys_get_temp_dir(), 'acadsyntra-research-');
        abort_unless(is_string($path), 503, 'Export storage is temporarily unavailable.');
        $zip = new \ZipArchive;
        try {
            abort_unless($zip->open($path, \ZipArchive::OVERWRITE) === true, 503, 'The document could not be created.');
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
            $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('word/document.xml', $document);
            $zip->addFromString('word/styles.xml', $styles);
            abort_unless($zip->close(), 503, 'The document could not be packaged.');
            return $path;
        } catch (\Throwable $e) { @unlink($path); throw $e; }
    }

    public function bibliography(ResearchProject $project): array
    {
        return $project->referenceLinks->pluck('reference')->filter()->values()
            ->map(fn($reference, $index) => app(AcademicReferenceService::class)->formatForProject($reference, $project, $index + 1))->all();
    }

    private function xml(string $text): string { return htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
    private function paragraph(string $text, ?string $style = null): string { return '<w:p>'.($style ? '<w:pPr><w:pStyle w:val="'.$style.'"/></w:pPr>' : '').'<w:r><w:t xml:space="preserve">'.$this->xml($text).'</w:t></w:r></w:p>'; }
    private function block(DOMNode $node): string
    {
        $tag = strtolower($node->nodeName);
        if ($tag === 'table') {
            $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/></w:tblPr>';
            foreach ($node->getElementsByTagName('tr') as $row) {
                $xml .= '<w:tr>';
                foreach ($row->childNodes as $cell) if (in_array($cell->nodeName, ['td', 'th'])) $xml .= '<w:tc>'.$this->paragraph($cell->textContent).'</w:tc>';
                $xml .= '</w:tr>';
            }
            return $xml.'</w:tbl>';
        }
        if (in_array($tag, ['ul', 'ol', 'div', 'section', 'article'])) {
            $xml = ''; foreach ($node->childNodes as $child) $xml .= $this->block($child); return $xml;
        }
        if ($node->nodeType === XML_TEXT_NODE) return trim($node->textContent) === '' ? '' : $this->paragraph($node->textContent);
        $style = preg_match('/^h([1-6])$/', $tag, $m) ? 'Heading'.min(3, (int) $m[1] + 1) : null;
        $runs = $tag === 'li' ? '<w:r><w:t>• </w:t></w:r>' : '';
        foreach ($node->childNodes as $child) $runs .= $this->inline($child);
        return '<w:p>'.($style ? '<w:pPr><w:pStyle w:val="'.$style.'"/></w:pPr>' : '').$runs.'</w:p>';
    }
    private function inline(DOMNode $node, string $properties = ''): string
    {
        if ($node->nodeName === 'br') return '<w:r><w:br/></w:r>';
        $properties .= match ($node->nodeName) { 'b', 'strong' => '<w:b/>', 'i', 'em' => '<w:i/>', 'u' => '<w:u w:val="single"/>', default => '' };
        if ($node->nodeType === XML_TEXT_NODE) return '<w:r>'.($properties ? '<w:rPr>'.$properties.'</w:rPr>' : '').'<w:t xml:space="preserve">'.$this->xml($node->textContent).'</w:t></w:r>';
        $xml = ''; foreach ($node->childNodes as $child) $xml .= $this->inline($child, $properties); return $xml;
    }
}
