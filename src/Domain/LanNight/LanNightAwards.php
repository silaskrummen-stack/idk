<?php

namespace App\Domain\LanNight;

/**
 * Award-Verleihung der LAN-Night (ohne Punkte, nur zum Spaß).
 *
 * Ablauf (awards.phase):  off → voting → closed → reveal (Award für Award) → summary
 *  - voting:  Handys (QR-Code) wählen pro Kategorie einen Spieler aus den Team-Listen, umentscheiden erlaubt
 *  - reveal:  die Spielleitung deckt Award für Award auf; der Bildschirm trommelt REVEAL_DELAY Sekunden,
 *             erst dann gibt der Server den Gewinner heraus (kein Spicken über die API)
 *  - summary: alle Gewinner auf einen Blick
 */
final class LanNightAwards
{
    public const REVEAL_DELAY = 3;
    private const MAX_VOTERS = 200;

    private const DEFAULT_SOURCE = "🤝 Bester Teamplayer\n🗣️ Lautester Spieler\n🐀 Unehrenhafter Spieler\n💦 Sweaty Tryhard\n💀 Größter Throw";

    public static function defaults(): array
    {
        return [
            'source' => self::DEFAULT_SOURCE,
            'categories' => self::parse(self::DEFAULT_SOURCE),
            'phase' => 'off',
            'index' => -1,
            'shownAt' => null,
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
                if ($a['index'] + 1 >= $count) {
                    throw new \InvalidArgumentException('Alle Awards sind schon vergeben');
                }
                $a['phase'] = 'reveal';
                $a['index']++;
                $a['shownAt'] = microtime(true);
                $s['screen'] = 'awards';
                break;

            case 'awards.summary':
                if ($a['phase'] !== 'reveal') {
                    throw new \InvalidArgumentException('Erst die Awards aufdecken');
                }
                $a['phase'] = 'summary';
                $a['index'] = $count - 1;
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
                break;

            case 'awards.config':
                if (in_array($a['phase'], ['reveal', 'summary'], true)) {
                    throw new \InvalidArgumentException('Nicht während der Verleihung ändern');
                }
                $categories = self::parse((string) ($in['source'] ?? ''));
                if (!$categories) {
                    throw new \InvalidArgumentException('Mindestens eine Kategorie (eine pro Zeile)');
                }
                // Andere Kategorien → alte Stimmen passen nicht mehr
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

    /** Bildschirm & Handys: Gewinner erst, wenn der Award aufgedeckt ist und die Trommel durch ist. */
    public static function publicView(array $s, ?string $voter = null): array
    {
        $a = $s['awards'];
        $results = self::results($s);
        $now = microtime(true);
        $visible = [];
        foreach ($results as $i => $r) {
            $done = $a['phase'] === 'summary'
                || ($a['phase'] === 'reveal' && ($i < $a['index'] || ($i === $a['index'] && $now >= $a['shownAt'] + self::REVEAL_DELAY)));
            $visible[] = $done ? $r : null;
        }

        return [
            'phase' => $a['phase'],
            'categories' => $a['categories'],
            'players' => self::players($s),
            'voterCount' => count($a['votes']),
            'index' => $a['index'],
            'revealAt' => $a['shownAt'] ? $a['shownAt'] + self::REVEAL_DELAY : null,
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
        $a['revealAt'] = $a['shownAt'] ? $a['shownAt'] + self::REVEAL_DELAY : null;

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

    /** Eine Kategorie pro Zeile, z. B. „🤝 Bester Teamplayer“ – führendes Emoji wird als Symbol genommen. */
    private static function parse(string $source): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $source) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $emoji = '🏆';
            // Erstes "Wort" ohne Buchstaben/Ziffern = Emoji
            if (preg_match('/^([^\p{L}\p{N}\s]+)\s+(.+)$/u', $line, $m)) {
                [$emoji, $line] = [$m[1], $m[2]];
            }
            $out[] = ['emoji' => $emoji, 'title' => mb_substr($line, 0, 40)];
        }

        return array_slice($out, 0, 12);
    }
}
