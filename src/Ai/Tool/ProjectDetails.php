<?php

namespace App\Ai\Tool;

use App\Domain\ProjectDashboard\ProjectCatalog;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tool für den Portfolio-Assistenten: liefert die ausführliche Beschreibung eines Projekts.
 *
 * Eine Quelle für alles:
 *   - Projekte mit eigener Seite (LAN-Party, CIV-Abend, krummen.de, Chatbot): Die fertige Projektseite wird intern
 *     gerendert (Sub-Request) und ihr Text ausgelesen – genau das, was Besucher im Browser sehen.
 *   - Alle anderen: der Text aus lib/test.php, den auch das Modal im Projekt-Log zeigt.
 *
 * Im Systemprompt steht von jedem Projekt nur eine Zeile; Details holt sich das Modell nur bei Bedarf.
 */
#[AsTool(
    name: 'projekt_details',
    description: 'Liefert die ausführliche Beschreibung eines Projekts aus Silas\' Portfolio (Aufgaben, Technik, Herausforderungen, Ergebnisse). Nutze das Tool, sobald nach Details zu einem bestimmten Projekt gefragt wird.',
)]
final class ProjectDetails
{
    private const MAX_LENGTH = 12000;

    public function __construct(
        private readonly ProjectCatalog $catalog,
        private readonly HttpKernelInterface $kernel,
    ) {
    }

    /**
     * @param string $titel Titel des Projekts, wie er in der Projektliste steht (z. B. "LoRaWAN" oder "Raumverwaltung")
     */
    public function __invoke(string $titel): string
    {
        $projects = $this->catalog->all();
        $project = $this->find($projects, $titel);

        if (null === $project) {
            return \sprintf(
                'Kein Projekt mit dem Titel "%s" gefunden. Verfügbare Projekte: %s',
                $titel,
                implode(', ', array_column($projects, 'title')),
            );
        }

        $url = $this->catalog->urls()[$project['title']] ?? '';
        $header = [
            '# '.$project['title'],
            \sprintf('Datum: %s · %s · Link: %s', $project['date'] ?? 'ohne Datum', 'produktiv' === ($project['category'] ?? '') ? 'beruflich' : 'privat', $url),
            '',
            trim($project['description'] ?? ''),
            '',
        ];

        $body = isset($project['route']) ? $this->pageText($url) : null;
        $body ??= $this->contentText($project['content'] ?? []);

        return mb_substr(implode("\n", $header).$body, 0, self::MAX_LENGTH);
    }

    /** Text aus dem 'content'-Array in lib/test.php */
    private function contentText(array $content): string
    {
        $lines = [];
        foreach ($content as $block) {
            match ($block['type'] ?? null) {
                'heading' => array_push($lines, '', '## '.$block['text']),
                'text' => $lines[] = $block['text'],
                default => null, // Bilder o. Ä. bringen dem Modell nichts
            };
        }

        return implode("\n", $lines);
    }

    /** Rendert eine Projektseite intern und gibt ihren Text zurück (oder null, wenn das nicht klappt). */
    private function pageText(string $url): ?string
    {
        if ('' === $url) {
            return null;
        }

        try {
            $response = $this->kernel->handle(Request::create($url), HttpKernelInterface::SUB_REQUEST, false);
            if (200 !== $response->getStatusCode()) {
                return null;
            }

            return $this->htmlToText((string) $response->getContent());
        } catch (\Throwable) {
            return null;
        }
    }

    private function htmlToText(string $html): ?string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        // Nur der Artikel, ohne „Nächstes Projekt“-Navigation und Skripte
        $nodes = $xpath->query(
            '//article//*[self::h2 or self::h3 or self::p or self::li or self::dt or self::dd or self::figcaption or self::strong]'
            .'[not(ancestor::nav)][not(ancestor::script)]',
        );
        if (false === $nodes || 0 === $nodes->length) {
            return null;
        }

        $lines = [];
        foreach ($nodes as $node) {
            // <strong> nur als eigene Zeile, wenn es nicht Teil eines Absatzes ist
            if ('strong' === $node->nodeName && null !== $node->parentNode && 'p' === $node->parentNode->nodeName) {
                continue;
            }
            $text = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
            if ('' === $text || end($lines) === $text) {
                continue;
            }
            $lines[] = match ($node->nodeName) {
                'h2', 'h3' => "\n## ".preg_replace('/^\d{2}\s*/', '', $text),
                'li' => '- '.$text,
                default => $text,
            };
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<array<string, mixed>> $projects
     *
     * @return array<string, mixed>|null
     */
    private function find(array $projects, string $title): ?array
    {
        $needle = mb_strtolower(trim($title));

        foreach ($projects as $project) {
            if (mb_strtolower($project['title']) === $needle) {
                return $project;
            }
        }
        foreach ($projects as $project) {
            $candidate = mb_strtolower($project['title']);
            if ('' !== $needle && (str_contains($candidate, $needle) || str_contains($needle, $candidate))) {
                return $project;
            }
        }

        return null;
    }
}
