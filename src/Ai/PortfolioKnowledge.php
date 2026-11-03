<?php

namespace App\Ai;

use App\Domain\AboutMe\AboutProfile;
use App\Domain\ProjectDashboard\ProjectCatalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Baut den Systemprompt für den Portfolio-Assistenten zusammen. Quellen (jeweils nur eine Stelle zum Pflegen):
 *   - prompts/assistent-regeln.md       Verhalten des Assistenten
 *   - AboutMe/lib/profil.php           Werdegang, Kenntnisse, Stärken, Hobbys (auch Seite „Über mich“)
 *   - prompts/ueber-mich.md             alles, was nicht auf der Website steht (Ziele, Arbeitsweise, FAQ)
 *   - ProjectDashboard/lib/test.php    Projektübersicht (auch Seite „Projekte“)
 *
 * Der Prompt ist bei jeder Anfrage identisch. Das ist Absicht: Anbieter cachen einen gleichbleibenden
 * Prompt-Anfang automatisch (ab ca. 1.024 Tokens) und rechnen ihn günstiger ab. Deshalb nichts Veränderliches
 * wie Datum oder Uhrzeit einbauen.
 */
final class PortfolioKnowledge
{
    private const SKILL_LEVELS = [1 => 'Grundlagen', 2 => 'gute Kenntnisse', 3 => 'sicher'];

    private ?string $systemPrompt = null;

    public function __construct(
        private readonly ProjectCatalog $projects,
        private readonly AboutProfile $profile,
        #[Autowire('%kernel.project_dir%/prompts')]
        private readonly string $directory,
    ) {
    }

    public function systemPrompt(): string
    {
        return $this->systemPrompt ??= implode("\n\n", [
            $this->read('assistent-regeln.md'),
            "# Über Silas\n\n".$this->read('ueber-mich.md'),
            $this->profileSection(),
            "## Projekte\n\nAlle Projekte aus seinem Portfolio, neueste zuerst. „beruflich“ = in der Ausbildung bzw. für Kunden entstanden, „privat“ = eigene Projekte.\n\n".$this->projectList(),
        ]);
    }

    private function profileSection(): string
    {
        $profile = $this->profile->all();
        $urls = $this->projects->urls();
        $out = [];

        $out[] = '## Werdegang (von der Seite „Über mich“, in seinen eigenen Worten)';
        foreach ($profile['stations'] as $station) {
            $facts = $station['facts'] ?? [];
            $out[] = \sprintf(
                "- %s – %s: %s%s\n  %s",
                $station['year'],
                $station['label'],
                $station['title'],
                [] !== $facts ? ' ('.implode(', ', $facts).')' : '',
                implode(' ', $station['text'] ?? []),
            );
        }

        $out[] = "\n## Kenntnisse (Selbsteinschätzung auf einer Skala von 1 bis 3)";
        foreach ($profile['skill_groups'] as $group => $skills) {
            $items = [];
            foreach ($skills as $name => $level) {
                $projects = $profile['skill_projects'][$name] ?? [];
                $items[] = \sprintf('%s: %s%s', $name, self::SKILL_LEVELS[$level] ?? $level, [] !== $projects ? ' – z. B. in '.implode(', ', $projects) : '');
            }
            $out[] = \sprintf('- %s: %s', $group, implode('; ', $items));
        }

        $out[] = "\n## Stärken mit Beleg";
        foreach ($profile['strengths'] as $strength) {
            $project = $strength['project'] ?? null;
            $out[] = \sprintf(
                '- %s: %s%s',
                $strength['name'],
                $strength['proof'],
                null !== $project ? \sprintf(' (Projekt: [%s](%s))', $project, $urls[$project] ?? '') : '',
            );
        }

        $out[] = "\n## Hobbys";
        foreach ($profile['hobbies'] as $hobby) {
            $out[] = \sprintf('- %s: %s', $hobby['title'], $hobby['text']);
        }

        return implode("\n", $out);
    }

    private function projectList(): string
    {
        $urls = $this->projects->urls();
        $lines = [];
        foreach ($this->projects->all() as $project) {
            $lines[] = \sprintf(
                '- [%s](%s) – %s, %s: %s',
                $project['title'],
                $urls[$project['title']] ?? '',
                $project['date'] ?? 'ohne Datum',
                'produktiv' === ($project['category'] ?? '') ? 'beruflich' : 'privat',
                trim($project['description'] ?? ''),
            );
        }

        return implode("\n", $lines);
    }

    private function read(string $file): string
    {
        $content = file_get_contents($this->directory.'/'.$file);
        if (false === $content) {
            throw new \RuntimeException('Prompt-Datei fehlt: prompts/'.$file);
        }

        // HTML-Kommentare sind Notizen für Silas, nicht für das Modell
        return trim(preg_replace('/<!--.*?-->/s', '', $content) ?? $content);
    }
}
