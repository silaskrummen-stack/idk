<?php

namespace App\Domain\LanNight;

/**
 * "Guess the Game" der LAN-Night.
 *
 * Pro Spiel bis zu 5 Bilder (Ordner lan-night-media/guess-the-game/<Nr>/, sortiert nach Dateiname).
 * Ablauf (guess.phase):  intro → picture → shown → picture → … → solved → picture (nächstes Spiel) … → done
 *  - picture: Bild auf dem Bildschirm, `seconds` Sekunden. Pro Team tippt EIN Handy einen Tipp ein (endgültig).
 *  - shown:   beide getippt oder Zeit um → Tipps SHOW_SECONDS Sekunden lang zeigen (✓/✗)
 *             → jemand richtig: solved, sonst nächstes Bild; nach dem letzten Bild: solved (keiner hat's)
 *  - solved:  Auflösung; die Spielleitung startet das nächste Spiel
 *  - done:    Endstand. Ratepunkte: erraten auf Bild n → 6 - n (Bild 1 = 5, Bild 5 = 1).
 *             Das Team mit mehr Ratepunkten bekommt `points` (Standard 3), Gleichstand: beide die Hälfte.
 */
final class LanNightGuess
{
    public const GAME = 'guess';
    public const SHOW_SECONDS = 2;
    public const MAX_PICTURES = 5;

    private const DEFAULT_SOURCE = <<<'TXT'
        Fallout: New Vegas | New Vegas, Fallout NV, FNV, Fallout Vegas
        Frostpunk 2 | Frostpunk II, Frostpunk Two, Frost Punk 2, Frostpunk
        Grand Theft Auto: Vice City | GTA Vice City, GTA: Vice City, Vice City, GTA VC, GTA 3 Vice City
        Dead by Daylight | DBD, Dead by Day Light, Dead by Daylite
        The Witcher 3 | Witcher 3, The Witcher 3: Wild Hunt, Witcher 3 Wild Hunt, Witcher III, TW3, Wild Hunt
        TXT;

    public static function defaults(): array
    {
        return [
            'source' => self::DEFAULT_SOURCE,
            'games' => self::parse(self::DEFAULT_SOURCE),
            'seconds' => 15,
            'points' => 3,
            'phase' => 'intro',
            'game' => -1,
            'picture' => 0,
            'endsAt' => null,
            'shownAt' => null,
            'guesses' => [null, null],   // pro Team für das aktuelle Bild: {text, correct}
            'results' => [],             // Spiel-Index → [Bild-Nr. Team 0 | null, Bild-Nr. Team 1 | null]
        ];
    }

    public static function normalize(array $s): array
    {
        $s['guess'] = array_merge(self::defaults(), $s['guess'] ?? []);

        return $s;
    }

    public static function needsAdvance(array $s): bool
    {
        $g = $s['guess'] ?? null;
        $now = microtime(true);

        return $g && (
            ($g['phase'] === 'picture' && ($now > $g['endsAt'] + 0.5 || self::bothIn($g)))
            || ($g['phase'] === 'shown' && $now >= $g['shownAt'] + self::SHOW_SECONDS)
        );
    }

    /** Läuft von selbst weiter (bei jedem Lesen/Schreiben geprüft). */
    public static function advance(array $s, array $counts): array
    {
        $g = $s['guess'];
        $now = microtime(true);

        if ($g['phase'] === 'picture' && ($now > $g['endsAt'] + 0.5 || self::bothIn($g))) {
            $g['phase'] = 'shown';
            $g['shownAt'] = $now;
        }
        if ($g['phase'] === 'shown' && $now >= $g['shownAt'] + self::SHOW_SECONDS) {
            $hit = false;
            $g['results'][$g['game']] ??= [null, null];
            foreach ([0, 1] as $t) {
                if ($g['guesses'][$t]['correct'] ?? false) {
                    $g['results'][$g['game']][$t] = $g['picture'] + 1;
                    $hit = true;
                }
            }
            if ($hit || $g['picture'] + 1 >= ($counts[$g['game']] ?? 0)) {
                $g['phase'] = 'solved';
            } else {
                $g['picture']++;
                $g['phase'] = 'picture';
                $g['endsAt'] = $now + $g['seconds'];
                $g['guesses'] = [null, null];
            }
        }
        $s['guess'] = $g;

        return $s;
    }

    public static function apply(array $s, string $action, array $in, array $counts): array
    {
        $g = $s['guess'];

        switch ($action) {
            case 'guess.open':
                $s['screen'] = 'guess';
                break;

            case 'guess.next':
                if (in_array($g['phase'], ['picture', 'shown'], true)) {
                    throw new \InvalidArgumentException('Das aktuelle Spiel läuft noch');
                }
                $next = $g['game'] + 1;
                if (!isset($g['games'][$next])) {
                    throw new \InvalidArgumentException('Keine weiteren Spiele');
                }
                if (($counts[$next] ?? 0) === 0) {
                    throw new \InvalidArgumentException(sprintf('Ordner %d hat keine Bilder (lan-night-media/guess-the-game/%d/)', $next + 1, $next + 1));
                }
                if ($g['phase'] === 'done') {
                    $s = self::unbookWin($s);
                }
                $g['game'] = $next;
                $g['picture'] = 0;
                $g['phase'] = 'picture';
                $g['endsAt'] = microtime(true) + $g['seconds'];
                $g['guesses'] = [null, null];
                $s['screen'] = 'guess';
                break;

            case 'guess.stop':
                // Zeit für das aktuelle Bild vorzeitig beenden
                if ($g['phase'] !== 'picture') {
                    throw new \InvalidArgumentException('Gerade läuft kein Bild');
                }
                $g['endsAt'] = microtime(true) - 1;
                break;

            case 'guess.set':
                // Korrektur der Spielleitung: Team hat Spiel X auf Bild N erraten (null = nicht erraten)
                $game = $in['game'] ?? null;
                $team = $in['team'] ?? null;
                $picture = $in['picture'] ?? null;
                if (!is_int($game) || !isset($g['games'][$game]) || !in_array($team, [0, 1], true)
                    || !($picture === null || (is_int($picture) && $picture >= 1 && $picture <= self::MAX_PICTURES))) {
                    throw new \InvalidArgumentException('Ungültige Korrektur');
                }
                $g['results'][$game] ??= [null, null];
                $g['results'][$game][$team] = $picture;
                if ($g['phase'] === 'done') {
                    $s['guess'] = $g;
                    $s = self::bookWin($s);
                    $g = $s['guess'];
                }
                break;

            case 'guess.done':
                if (in_array($g['phase'], ['picture', 'shown'], true)) {
                    throw new \InvalidArgumentException('Das aktuelle Spiel läuft noch');
                }
                if ($g['game'] < 0) {
                    throw new \InvalidArgumentException('Es wurde noch kein Spiel gespielt');
                }
                $g['phase'] = 'done';
                $s['screen'] = 'guess';
                $s['guess'] = $g;
                $s = self::bookWin($s);
                $g = $s['guess'];
                break;

            case 'guess.close':
                if (in_array($g['phase'], ['picture', 'shown'], true)) {
                    // Abgebrochenes Spiel kommt beim nächsten Mal von vorne
                    unset($g['results'][$g['game']]);
                    $g['game']--;
                    $g['phase'] = $g['game'] < 0 ? 'intro' : 'solved';
                }
                $s['screen'] = 'scoreboard';
                break;

            case 'guess.reset':
                $s = self::unbookWin($s);
                $config = array_intersect_key($g, array_flip(['source', 'games', 'seconds', 'points']));
                $g = array_merge(self::defaults(), $config);
                break;

            case 'guess.config':
                if (in_array($g['phase'], ['picture', 'shown'], true)) {
                    throw new \InvalidArgumentException('Nicht während eines Spiels ändern');
                }
                $games = self::parse((string) ($in['source'] ?? ''));
                if (!$games) {
                    throw new \InvalidArgumentException('Mindestens ein Spiel (eins pro Zeile)');
                }
                $g['source'] = (string) $in['source'];
                $g['games'] = $games;
                $g['seconds'] = min(120, max(5, (int) ($in['seconds'] ?? $g['seconds'])));
                $g['points'] = min(50, max(0, (int) ($in['points'] ?? $g['points'])));
                break;

            default:
                throw new \InvalidArgumentException('Unbekannte Aktion');
        }
        $s['guess'] = $g;

        return $s;
    }

    /** Tipp vom Handy (öffentlich): {team, text, game, picture} – einer pro Team und Bild, endgültig. */
    public static function submit(array $s, array $in): array
    {
        $g = $s['guess'];
        $team = $in['team'] ?? null;
        $text = trim(preg_replace('/\s+/u', ' ', (string) ($in['text'] ?? '')));

        if (!in_array($team, [0, 1], true) || $text === '') {
            throw new \InvalidArgumentException('Bitte einen Spielnamen eintippen');
        }
        if ($g['phase'] !== 'picture' || ($in['game'] ?? null) !== $g['game'] || ($in['picture'] ?? null) !== $g['picture']) {
            throw new \InvalidArgumentException('Zu spät – das Bild ist schon vorbei');
        }
        if ($g['guesses'][$team] !== null) {
            throw new \InvalidArgumentException('Euer Team hat für dieses Bild schon getippt');
        }

        $text = mb_substr($text, 0, 60);
        $g['guesses'][$team] = ['text' => $text, 'correct' => self::matches($text, $g['games'][$g['game']])];
        $s['guess'] = $g;

        return $s;
    }

    /** Bildschirm & Handys: keine Lösung und keine Tipps, bevor sie gezeigt werden. */
    public static function publicView(array $g, array $counts): array
    {
        $shown = in_array($g['phase'], ['shown', 'solved'], true);
        $current = $g['games'][$g['game']] ?? null;
        $playing = in_array($g['phase'], ['picture', 'shown', 'solved'], true) && $current;

        return [
            'phase' => $g['phase'],
            'game' => $g['game'],
            'total' => count($g['games']),
            'picture' => $g['picture'],
            'pictures' => $playing ? ($counts[$g['game']] ?? 0) : 0,
            'seconds' => $g['seconds'],
            'points' => $g['points'],
            'endsAt' => $g['endsAt'],
            'showUntil' => $g['shownAt'] ? $g['shownAt'] + self::SHOW_SECONDS : null,
            'image' => $playing ? self::imageUrl($g) : null,
            'locked' => [$g['guesses'][0] !== null, $g['guesses'][1] !== null],
            'guesses' => $shown ? $g['guesses'] : [null, null],
            'answer' => $g['phase'] === 'solved' ? $current['name'] : null,
            'solvedBy' => $g['phase'] === 'solved' ? ($g['results'][$g['game']] ?? [null, null]) : null,
            'score' => self::score($g),
            'played' => $g['phase'] === 'done' ? self::playedList($g) : null,
        ];
    }

    public static function adminView(array $g, array $counts): array
    {
        $g['counts'] = $counts;
        $g['score'] = self::score($g);
        $g['image'] = $g['game'] >= 0 ? self::imageUrl($g) : null;
        $g['showUntil'] = $g['shownAt'] ? $g['shownAt'] + self::SHOW_SECONDS : null;
        $g['locked'] = [$g['guesses'][0] !== null, $g['guesses'][1] !== null];

        return $g;
    }

    /** Darf dieses Bild schon gezeigt werden? (Kein Vorausschauen über die URL.) */
    public static function imageVisible(array $g, int $game, int $picture): bool
    {
        if ($g['phase'] === 'done') {
            return $game <= $g['game'];
        }

        return $game < $g['game'] || ($game === $g['game'] && $picture <= $g['picture'] && $g['phase'] !== 'intro');
    }

    /**
     * Passt der Tipp? Groß/klein, Umlaute, Satzzeichen, "The", römische Zahlen und kleine Tippfehler sind egal.
     * Erlaubt sind der Name und alle Varianten hinter dem "|".
     */
    public static function matches(string $guess, array $game): bool
    {
        $n = self::simplify($guess);
        if ($n === '') {
            return false;
        }
        foreach (array_merge([$game['name']], $game['aliases']) as $variant) {
            $v = self::simplify($variant);
            if ($v === '') {
                continue;
            }
            if ($n === $v) {
                return true;
            }
            // Tippfehler (ein Buchstabendreher zählt als 2): ab 5 Zeichen 1, ab 10 Zeichen 2, ab 14 Zeichen 3.
            // Zahlen müssen exakt stimmen – sonst wäre "Witcher 2" plötzlich "Witcher 3"
            $len = strlen($v);
            if ($len >= 5 && preg_replace('/\D/', '', $n) === preg_replace('/\D/', '', $v)
                && levenshtein($n, $v) <= ($len >= 14 ? 3 : ($len >= 10 ? 2 : 1))) {
                return true;
            }
            // Mehr geschrieben als nötig, z. B. "GTA Vice City Definitive Edition"
            if ($len >= 6 && str_contains($n, $v)) {
                return true;
            }
        }

        return false;
    }

    private static function simplify(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', '&' => ' and ']);
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        // Römische Zahlen und ausgeschriebene Zahlen als Ziffern
        $s = preg_replace_callback('/\b(ii|iii|iv|vi|two|three|four|zwei|drei|vier)\b/', fn ($m) => [
            'ii' => '2', 'iii' => '3', 'iv' => '4', 'vi' => '6', 'two' => '2', 'three' => '3', 'four' => '4',
            'zwei' => '2', 'drei' => '3', 'vier' => '4',
        ][$m[1]], $s);
        $s = preg_replace('/^\s*the\s+/', '', $s);

        return str_replace(' ', '', $s);
    }

    /** Ratepunkte pro Team: erraten auf Bild n → 6 - n. */
    private static function score(array $g): array
    {
        $score = [0, 0];
        foreach ($g['results'] as $r) {
            foreach ([0, 1] as $t) {
                if ($r[$t] !== null) {
                    $score[$t] += self::MAX_PICTURES + 1 - $r[$t];
                }
            }
        }

        return $score;
    }

    private static function playedList(array $g): array
    {
        $out = [];
        foreach ($g['games'] as $i => $game) {
            if ($i > $g['game']) {
                break;
            }
            $out[] = ['name' => $game['name'], 'solvedBy' => $g['results'][$i] ?? [null, null]];
        }

        return $out;
    }

    private static function imageUrl(array $g): string
    {
        return sprintf('/lan-night/guess-image/%d/%d', $g['game'], $g['picture']);
    }

    private static function bothIn(array $g): bool
    {
        return $g['guesses'][0] !== null && $g['guesses'][1] !== null;
    }

    private static function bookWin(array $s): array
    {
        $s = self::unbookWin($s);
        [$a, $b] = self::score($s['guess']);
        $points = $s['guess']['points'];
        if ($points < 1 || ($a === 0 && $b === 0)) {
            return $s;
        }
        $book = function (int $team, int $delta, string $reason) use (&$s) {
            $s['log'][] = ['id' => bin2hex(random_bytes(6)), 'at' => microtime(true), 'team' => $team, 'delta' => $delta, 'reason' => $reason, 'game' => self::GAME];
        };
        if ($a === $b) {
            $book(0, intdiv($points + 1, 2), sprintf('Guess the Game (Unentschieden %d:%d)', $a, $b));
            $book(1, intdiv($points + 1, 2), sprintf('Guess the Game (Unentschieden %d:%d)', $a, $b));
        } else {
            $book($a > $b ? 0 : 1, $points, sprintf('Guess the Game (%d:%d)', $a, $b));
        }

        return $s;
    }

    private static function unbookWin(array $s): array
    {
        $s['log'] = array_values(array_filter($s['log'], fn ($e) => ($e['game'] ?? null) !== self::GAME));

        return $s;
    }

    /** Ein Spiel pro Zeile: "Anzeigename | Variante, Variante, …" */
    private static function parse(string $source): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $source) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$name, $aliases] = array_pad(explode('|', $line, 2), 2, '');
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $out[] = [
                'name' => mb_substr($name, 0, 60),
                'aliases' => array_values(array_filter(array_map('trim', explode(',', $aliases)))),
            ];
        }

        return $out;
    }
}
