<?php

namespace App\Ai\Tool;

use App\Domain\ProjectDashboard\ProjectCatalog;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Tool für den Portfolio-Assistenten: liefert den ausführlichen Text eines Projekts aus lib/test.php.
 *
 * Im Systemprompt steht von jedem Projekt nur eine Zeile. Fragt jemand genauer nach, ruft das Modell dieses Tool
 * auf und bekommt die ganze Projektbeschreibung – so bleibt der Prompt klein (schneller, günstiger),
 * und trotzdem kann der Assistent in die Tiefe gehen.
 */
#[AsTool(
    name: 'projekt_details',
    description: 'Liefert die ausführliche Beschreibung eines Projekts aus Silas\' Portfolio (Aufgaben, Technik, Herausforderungen, Ergebnisse). Nutze das Tool, sobald nach Details zu einem bestimmten Projekt gefragt wird.',
)]
final class ProjectDetails
{
    public function __construct(private readonly ProjectCatalog $catalog)
    {
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
        $lines = [
            '# '.$project['title'],
            \sprintf('Datum: %s · %s · Link: %s', $project['date'] ?? 'ohne Datum', 'produktiv' === ($project['category'] ?? '') ? 'beruflich' : 'privat', $url),
            '',
            trim($project['description'] ?? ''),
        ];

        foreach ($project['content'] ?? [] as $block) {
            match ($block['type'] ?? null) {
                'heading' => array_push($lines, '', '## '.$block['text']),
                'text' => $lines[] = $block['text'],
                default => null, // Bilder o. Ä. bringen dem Modell nichts
            };
        }

        if (empty($project['content'])) {
            $lines[] = '';
            $lines[] = '(Zu diesem Projekt gibt es eine eigene Projektseite mit mehr Inhalt, siehe Link.)';
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

        // 1. exakter Titel, 2. Titel enthält Suchbegriff (oder umgekehrt)
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
