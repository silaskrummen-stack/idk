<?php

namespace App\Domain\ProjectDashboard;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Alle Projekte aus lib/test.php, neueste zuerst. Jedes Projekt bekommt einen festen Anker (z. B. "projekt-webinar"),
 * damit man von anderen Seiten direkt auf ein Projekt verlinken kann: /projects#projekt-webinar öffnet das Modal.
 */
class ProjectCatalog
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public function all(): array
    {
        $projects = require __DIR__ . '/lib/test.php';

        foreach ($projects as &$project) {
            $project['anchor'] = self::anchor($project['title']);
        }
        unset($project);

        // Neueste zuerst: "09/2026" → 202609, damit sich die Daten als Zahl vergleichen lassen
        usort($projects, fn (array $a, array $b) => self::sortKey($b) <=> self::sortKey($a));

        return $projects;
    }

    /** Titel → Link: eigene Projektseite, sonst das Modal im Projekt-Log. */
    public function urls(): array
    {
        $urls = [];
        foreach ($this->all() as $project) {
            $urls[$project['title']] = isset($project['route'])
                ? $this->urls->generate($project['route'])
                : $this->urls->generate('project_dashboard') . '#' . $project['anchor'];
        }

        return $urls;
    }

    private static function anchor(string $title): string
    {
        $slug = strtr(mb_strtolower($title), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);

        return 'projekt-' . trim(preg_replace('~[^a-z0-9]+~', '-', $slug), '-');
    }

    private static function sortKey(array $project): int
    {
        // Projekte ohne gültiges Datum (Format MM/YYYY) landen am Ende
        if (!preg_match('~^(\d{1,2})/(\d{4})$~', trim($project['date'] ?? ''), $m)) {
            return 0;
        }

        return (int) $m[2] * 100 + (int) $m[1];
    }
}
