<?php

namespace App\Domain\LanNight;

/**
 * Award-Verleihung der LAN-Night (ohne Punkte, nur zum Spaß).
 *
 * Ablauf (awards.phase):  off → voting → closed → reveal (Award für Award) → summary
 *  - voting:  Handys (QR-Code) wählen pro Kategorie einen Spieler aus den Team-Listen, umentscheiden erlaubt
 *  - reveal:  die Spielleitung zeigt einen Award (awards.next) – Bildschirm: Kategorie + Satz, nach DRUM_DELAY
 *             Sekunden setzt der Trommelwirbel ein. Den Gewinner gibt es erst mit awards.show (Fanfare),
 *             vorher verrät die API ihn nicht. Weiter zum nächsten Award erst, wenn der Gewinner gezeigt wurde.
 *  - summary: alle Gewinner auf einen Blick
 *
 * Kategorien: eine pro Zeile, Emoji vorne, optional ein Satz für die Leute hinter „|“:
 *   💀 Größter Throw | Welcher Spieler hat einen Play gemacht, den ihr nie vergessen werdet?
 */
final class LanNightAwards
{
    public const DRUM_DELAY = 3;
    private const MAX_VOTERS = 200;

    private const DEFAULT_SOURCE = <<<'TXT'
        🤝 Bester Teamplayer | Wer hat heute am meisten für sein Team getan – auch wenn's keiner gemerkt hat?
        🗣️ Lautester Spieler | Wen hat man heute garantiert durch die ganze Halle gehört?
        🐀 Unehrenhafter Spieler | Wer hat sich heute mit den fiesesten Tricks durchgemogelt?
        💦 Sweaty Tryhard | Wer hat jede Runde gespielt, als ginge es um ein Preisgeld?
        💀 Größter Throw | Welcher Spieler hat einen Play hingelegt, der so schlecht war, dass ihr ihn nie vergessen werdet?
        TXT;

    public static function defaults(): array
    {
        return [
            'source' => self::DEFAULT_SOURCE,
            'categories' => self::parse(self::DEFAULT_SOURCE),
            'phase' => 'off',
            'index' => -1,
            'shownAt' => null,      // wann der aktuelle Award auf den Bildschirm kam
            'revealed' => false,    // Gewinner des aktuellen Awards schon gezeigt?
            'votes' => [],
        ];
    }

    public static function normalize(array $s): array
    {
        $s['awards'] = array_merge(self::defaults(), $s['awards'] ?? []);

        return $s;
    }

    public static function apply(array $s, string $action, array $in): array
    {
        $a = $s['awards'];
        $count = count($a['categories']);

        switch ($action) {
            case 'awards.open':
                $a['phase'] = 'voting';
                $a['index'] = -1;
                $s['screen'] = 'awards';
                break;

            case 'awards.close':
                if ($a['phase'] !== 'voting') {
                    throw new \InvalidArgumentException('Die Abstimmung läuft gerade nicht');
                }
                $a['phase'] = 'closed';
                $s['screen'] = 'awards';
                break;

            case 'awards.next':
                if (!in_array($a['phase'], ['voting', 'closed', 'reveal'], true)) {
                    throw new \InvalidArgumentException('Erst die Abstimmung öffnen');
                }
                if ($a['phase'] === 'reveal' && !$a['revealed']) {
                    throw new \InvalidArgumentException('Erst den Gewinner zeigen');
                }
                if ($a['index'] + 1 >= $count) {
                    throw new \InvalidArgumentException('Alle Awards sind schon vergeben');
                }
                $a['phase'] = 'reveal';
                $a['index']++;
                $a['shownAt'] = microtime(true);
                $a['revealed'] = false;
                $s['screen'] = 'awards';
                break;

            case 'awards.show':
                if ($a['phase'] !== 'reveal') {
                    throw new \InvalidArgumentException('Erst einen Award aufrufen');
                }
                if ($a['revealed']) {
                    throw new \InvalidArgumentException('Der Gewinner steht schon da');
                }
                $a['revealed'] = true;
                $s['screen'] = 'awards';
                break;

            case 'awards.summary':
                if ($a['phase'] !== 'reveal') {
                    throw new \InvalidArgumentException('Erst die Awards aufdecken');
                }
                $a['phase'] = 'summary';
                $a['index'] = $count - 1;
                $a['revealed'] = true;
                $s['screen'] = 'awards';
                break;

            case 'awards.hide':
                $s['screen'] = 'scoreboard';
                break;

            case 'awards.reset':
                $a['votes'] = [];
                $a['phase'] = 'off';
                $a['index'] = -1;
                $a['shownAt'] = null;
                $a['revealed'] = false;
                break;

            case 'awards.config':
                if (in_array($a['phase'], ['reveal', 'summary'], true)) {
                    throw new \InvalidArgumentException('Nicht während der Verleihung ändern');
                }
                $categories = self::parse((string) ($in['source'] ?? ''));
                if (!$categories) {
                    throw new \InvalidArgumentException('Mindestens eine Kategorie (eine pro Zeile)');
                }
                // Andere Kategorien → alte Stimmen passen nicht mehr (nur Sätze geändert: Stimmen bleiben)
                if (array_column($categories, 'title') !== array_column($a['categories'], 'title')) {
                    $a['votes'] = [];
                }
                $a['source'] = (string) $in['source'];
                $a['categories'] = $categories;
                break;

            default:
                throw new \InvalidArgumentException('Unbekannte Aktion');
        }
        $s['awards'] = $a;

        return $s;
    }

    /** Stimme vom Handy (öffentlich): {voter, category, player} – player "" nimmt die Stimme zurück. */
    public static function vote(array $s, array $in): array
    {
        $a = $s['awards'];
        $voter = (string) ($in['voter'] ?? '');
        $cat = $in['category'] ?? null;
        $player = (string) ($in['player'] ?? '');

        if (!preg_match('/^[a-z0-9]{8,40}$/i', $voter) || !is_int($cat) || !isset($a['categories'][$cat])) {
            throw new \InvalidArgumentException('Ungültige Stimme');
        }
        if ($a['phase'] !== 'voting') {
            throw new \InvalidArgumentException('Die Abstimmung ist gerade nicht offen');
        }
        if ($player !== '' && !in_array($player, array_column(self::players($s), 'name'), true)) {
            throw new \InvalidArgumentException('Diesen Spieler gibt es nicht');
        }
        if (!isset($a['votes'][$voter]) && count($a['votes']) >= self::MAX_VOTERS) {
            throw new \InvalidArgumentException('Zu viele Handys');
        }

        $ballot = $a['votes'][$voter] ?? [];
        if ($player === '') {
            unset($ballot[(string) $cat]);
        } else {
            $ballot[(string) $cat] = $player;
        }
        $a['votes'][$voter] = (object) $ballot; // als JSON-Objekt speichern, auch wenn die Schlüssel 0, 1, … sind
        $s['awards'] = $a;

        return $s;
    }

    /** Bildschirm & Handys: Gewinner erst, wenn die Spielleitung ihn gezeigt hat. */
    public static function publicView(array $s, ?string $voter = null): array
    {
        $a = $s['awards'];
        $results = self::results($s);
        $visible = [];
        foreach ($results as $i => $r) {
            $done = $a['phase'] === 'summary'
                || ($a['phase'] === 'reveal' && ($i < $a['index'] || ($i === $a['index'] && $a['revealed'])));
            $visible[] = $done ? $r : null;
        }

        return [
            'phase' => $a['phase'],
            'categories' => $a['categories'],
            'players' => self::players($s),
            'voterCount' => count($a['votes']),
            'index' => $a['index'],
            'revealed' => $a['revealed'],
            'drumAt' => $a['shownAt'] ? $a['shownAt'] + self::DRUM_DELAY : null,
            'results' => $visible,
            'myBallot' => $voter !== null ? (object) ($a['votes'][$voter] ?? []) : null,
        ];
    }

    /** Spielleitung: alles inkl. Zwischenstand jeder Kategorie. */
    public static function adminView(array $s): array
    {
        $a = $s['awards'];
        unset($a['votes']);
        $a['players'] = self::players($s);
        $a['voterCount'] = count($s['awards']['votes']);
        $a['results'] = self::results($s);
        $a['drumAt'] = $a['shownAt'] ? $a['shownAt'] + self::DRUM_DELAY : null;

        return $a;
    }

    /** Alle Spieler aus beiden Teams (Name eindeutig, erstes Team gewinnt). */
    public static function players(array $s): array
    {
        $players = [];
        foreach ($s['teams'] as $t => $team) {
            foreach ($team['members'] as $name) {
                if (!in_array($name, array_column($players, 'name'), true)) {
                    $players[] = ['name' => $name, 'team' => $t];
                }
            }
        }

        return $players;
    }

    /** Pro Kategorie: Stimmen je Spieler (absteigend) und die Gewinner (bei Gleichstand mehrere). */
    private static function results(array $s): array
    {
        $out = [];
        foreach ($s['awards']['categories'] as $i => $cat) {
            $count = [];
            foreach ($s['awards']['votes'] as $ballot) {
                $name = ((array) $ballot)[(string) $i] ?? null;
                if ($name !== null) {
                    $count[$name] = ($count[$name] ?? 0) + 1;
                }
            }
            arsort($count);
            $top = $count ? max($count) : 0;
            $out[] = [
                'winners' => $top ? array_keys(array_filter($count, fn ($n) => $n === $top)) : [],
                'votes' => $top,
                'tally' => array_map(fn ($name, $n) => ['name' => $name, 'votes' => $n], array_keys($count), $count),
            ];
        }

        return $out;
    }

    /**
     * Eine Kategorie pro Zeile, z. B. „🤝 Bester Teamplayer“ – führendes Emoji wird als Symbol genommen.
     * Optional ein Satz dazu: „💀 Größter Throw | Welcher Spieler …?“ (oder „Größter Throw: "Welcher Spieler …?"“).
     */
    private static function parse(string $source): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $source) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $description = '';
            if (str_contains($line, '|')) {
                [$line, $description] = array_map('trim', explode('|', $line, 2));
            } elseif (preg_match('/^(.+?):\s*["„“”](.+?)["“”]?$/u', $line, $m)) {
                [$line, $description] = [trim($m[1]), trim($m[2])];
            }
            $description = trim($description, " \t\"„“”");
            if ($line === '') {
                continue;
            }
            $emoji = '🏆';
            // Erstes "Wort" ohne Buchstaben/Ziffern = Emoji
            if (preg_match('/^([^\p{L}\p{N}\s]+)\s+(.+)$/u', $line, $m)) {
                [$emoji, $line] = [$m[1], $m[2]];
            }
            $out[] = ['emoji' => $emoji, 'title' => mb_substr($line, 0, 40), 'description' => mb_substr($description, 0, 160)];
        }

        return array_slice($out, 0, 12);
    }
}
