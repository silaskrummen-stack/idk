<?php

namespace App\Domain\LanNight;

/**
 * "Guess the Price" der LAN-Night.
 *
 * Ein Bild pro Gegenstand: lan-night-media/guess-the-price/<Nr>.png (oder jpg/webp …).
 * Ablauf (price.phase):  intro → item → locked → reveal → item … → done
 *  - item:    Gegenstand + Bild auf dem Bildschirm, `seconds` Sekunden. Pro Team tippt EIN Handy einen Preis (endgültig).
 *  - locked:  beide getippt oder Zeit um → beide Tipps werden gezeigt
 *  - reveal:  REVEAL_DELAY Sekunden später automatisch: der Preis zählt hoch, das nähere Team gewinnt die Runde
 *             (gleich nah → beide). Danach startet die Spielleitung den nächsten Gegenstand.
 *  - done:    Das Team mit mehr gewonnenen Runden bekommt `points` (Standard 3), Gleichstand: beide die Hälfte.
 */
final class LanNightPrice
{
    public const GAME = 'price';
    public const REVEAL_DELAY = 3;

    private const DEFAULT_SOURCE = <<<'TXT'
        UVP GeForce RTX 5080 | 1.169,00
        Karambit | Case Hardened (Factory New) | 1.370,00
        Leviathan – die Yacht von Gabe Newell | 430.000.000,00
        Gesamtumsatz Electronic Arts 2025 | 7.463.000.000,00
        UVP Logitech G502 Maus | 59,00
        TXT;

    public static function defaults(): array
    {
        return [
            'source' => self::DEFAULT_SOURCE,
            'items' => self::parseItems(self::DEFAULT_SOURCE),
            'seconds' => 30,
            'points' => 3,
            'phase' => 'intro',
            'item' => -1,
            'endsAt' => null,
            'lockedAt' => null,
            'guesses' => [null, null],   // pro Team: {value, text}
            'results' => [],             // Gegenstand-Index → {guesses: [v|null, v|null], price, override: null|'0'|'1'|'both'|'none'}
        ];
    }

    public static function normalize(array $s): array
    {
        $s['price'] = array_merge(self::defaults(), $s['price'] ?? []);

        return $s;
    }

    public static function needsAdvance(array $s): bool
    {
        $p = $s['price'] ?? null;
        $now = microtime(true);

        return $p && (
            ($p['phase'] === 'item' && ($now > $p['endsAt'] + 0.5 || self::bothIn($p)))
            || ($p['phase'] === 'locked' && $now >= $p['lockedAt'] + self::REVEAL_DELAY)
        );
    }

    public static function advance(array $s): array
    {
        $p = $s['price'];
        $now = microtime(true);
        if ($p['phase'] === 'item' && ($now > $p['endsAt'] + 0.5 || self::bothIn($p))) {
            $p['phase'] = 'locked';
            $p['lockedAt'] = $now;
        }
        if ($p['phase'] === 'locked' && $now >= $p['lockedAt'] + self::REVEAL_DELAY) {
            $p['phase'] = 'reveal';
            $p['results'][$p['item']] = [
                'guesses' => [$p['guesses'][0]['value'] ?? null, $p['guesses'][1]['value'] ?? null],
                'price' => $p['items'][$p['item']]['price'],
                'override' => null,
            ];
        }
        $s['price'] = $p;

        return $s;
    }

    public static function apply(array $s, string $action, array $in, array $images): array
    {
        $p = $s['price'];

        switch ($action) {
            case 'price.open':
                $s['screen'] = 'price';
                break;

            case 'price.next':
                if (in_array($p['phase'], ['item', 'locked'], true)) {
                    throw new \InvalidArgumentException('Die aktuelle Runde läuft noch');
                }
                $next = $p['item'] + 1;
                if (!isset($p['items'][$next])) {
                    throw new \InvalidArgumentException('Keine weiteren Gegenstände');
                }
                if ($p['phase'] === 'done') {
                    $s = self::unbookWin($s);
                }
                $p['item'] = $next;
                $p['phase'] = 'item';
                $p['endsAt'] = microtime(true) + $p['seconds'];
                $p['lockedAt'] = null;
                $p['guesses'] = [null, null];
                $s['screen'] = 'price';
                break;

            case 'price.stop':
                if ($p['phase'] !== 'item') {
                    throw new \InvalidArgumentException('Gerade läuft keine Runde');
                }
                $p['endsAt'] = microtime(true) - 1;
                break;

            case 'price.reveal':
                // Sofort auflösen, ohne die 3 Sekunden abzuwarten
                if (!in_array($p['phase'], ['item', 'locked'], true)) {
                    throw new \InvalidArgumentException('Gerade läuft keine Runde');
                }
                $p['endsAt'] = microtime(true) - 1;
                $p['lockedAt'] = microtime(true) - self::REVEAL_DELAY;
                $p['phase'] = 'locked';
                break;

            case 'price.guess':
                // Spielleitung trägt einen Tipp für ein Team ein (z. B. wenn es laut gerufen wurde) – auch nachträglich
                $team = $in['team'] ?? null;
                if (!in_array($team, [0, 1], true)) {
                    throw new \InvalidArgumentException('Team fehlt');
                }
                $value = self::parseAmount((string) ($in['text'] ?? ''));
                if ($value === null) {
                    throw new \InvalidArgumentException('Das ist kein Preis');
                }
                if ($p['phase'] === 'item' || $p['phase'] === 'locked') {
                    $p['guesses'][$team] = ['value' => $value, 'text' => (string) $in['text']];
                } elseif ($p['phase'] === 'reveal' || $p['phase'] === 'done') {
                    $p['results'][$p['item']]['guesses'][$team] = $value;
                    $p['guesses'][$team] = ['value' => $value, 'text' => (string) $in['text']];
                    if ($p['phase'] === 'done') {
                        $s['price'] = $p;
                        $s = self::bookWin($s);
                        $p = $s['price'];
                    }
                } else {
                    throw new \InvalidArgumentException('Gerade läuft keine Runde');
                }
                break;

            case 'price.set':
                // Korrektur: wer hat die Runde gewonnen? auto = wer näher dran war
                $item = $in['item'] ?? null;
                $winner = $in['winner'] ?? 'auto';
                if (!is_int($item) || !isset($p['results'][$item]) || !in_array($winner, ['auto', '0', '1', 'both', 'none'], true)) {
                    throw new \InvalidArgumentException('Ungültige Korrektur');
                }
                $p['results'][$item]['override'] = $winner === 'auto' ? null : $winner;
                if ($p['phase'] === 'done') {
                    $s['price'] = $p;
                    $s = self::bookWin($s);
                    $p = $s['price'];
                }
                break;

            case 'price.done':
                if (in_array($p['phase'], ['item', 'locked'], true)) {
                    throw new \InvalidArgumentException('Die aktuelle Runde läuft noch');
                }
                if (!$p['results']) {
                    throw new \InvalidArgumentException('Es wurde noch keine Runde gespielt');
                }
                $p['phase'] = 'done';
                $s['screen'] = 'price';
                $s['price'] = $p;
                $s = self::bookWin($s);
                $p = $s['price'];
                break;

            case 'price.close':
                if (in_array($p['phase'], ['item', 'locked'], true)) {
                    $p['item']--;
                    $p['phase'] = $p['item'] < 0 ? 'intro' : 'reveal';
                    $p['guesses'] = [null, null];
                }
                $s['screen'] = 'scoreboard';
                break;

            case 'price.reset':
                $s = self::unbookWin($s);
                $config = array_intersect_key($p, array_flip(['source', 'items', 'seconds', 'points']));
                $p = array_merge(self::defaults(), $config);
                break;

            case 'price.config':
                if (in_array($p['phase'], ['item', 'locked'], true)) {
                    throw new \InvalidArgumentException('Nicht während einer Runde ändern');
                }
                $items = self::parseItems((string) ($in['source'] ?? ''));
                if (!$items) {
                    throw new \InvalidArgumentException('Mindestens ein Gegenstand: „Name | Preis“');
                }
                $p['source'] = (string) $in['source'];
                $p['items'] = $items;
                $p['seconds'] = min(180, max(5, (int) ($in['seconds'] ?? $p['seconds'])));
                $p['points'] = min(50, max(0, (int) ($in['points'] ?? $p['points'])));
                break;

            default:
                throw new \InvalidArgumentException('Unbekannte Aktion');
        }
        $s['price'] = $p;

        return $s;
    }

    /** Tipp vom Handy (öffentlich): {team, text, item} – einer pro Team und Gegenstand, endgültig. */
    public static function submit(array $s, array $in): array
    {
        $p = $s['price'];
        $team = $in['team'] ?? null;
        $text = mb_substr(trim((string) ($in['text'] ?? '')), 0, 40);

        if (!in_array($team, [0, 1], true)) {
            throw new \InvalidArgumentException('Team fehlt');
        }
        $value = self::parseAmount($text);
        if ($value === null) {
            throw new \InvalidArgumentException('Das ist kein Preis – z. B. „1.250“, „430 Mio“ oder „7,4 Mrd“');
        }
        if ($p['phase'] !== 'item' || ($in['item'] ?? null) !== $p['item']) {
            throw new \InvalidArgumentException('Zu spät – die Runde ist schon vorbei');
        }
        if ($p['guesses'][$team] !== null) {
            throw new \InvalidArgumentException('Euer Team hat schon getippt');
        }

        $p['guesses'][$team] = ['value' => $value, 'text' => $text];
        $s['price'] = $p;

        return $s;
    }

    public static function publicView(array $p, array $images): array
    {
        $current = $p['items'][$p['item']] ?? null;
        $playing = in_array($p['phase'], ['item', 'locked', 'reveal'], true) && $current;
        $shown = in_array($p['phase'], ['locked', 'reveal'], true);
        $result = $p['phase'] === 'reveal' ? ($p['results'][$p['item']] ?? null) : null;

        return [
            'phase' => $p['phase'],
            'item' => $p['item'],
            'total' => count($p['items']),
            'seconds' => $p['seconds'],
            'points' => $p['points'],
            'endsAt' => $p['endsAt'],
            'revealAt' => $p['lockedAt'] ? $p['lockedAt'] + self::REVEAL_DELAY : null,
            'name' => $playing ? $current['name'] : null,
            'image' => $playing && ($images[$p['item']] ?? false) ? self::imageUrl($p['item']) : null,
            'locked' => [$p['guesses'][0] !== null, $p['guesses'][1] !== null],
            'guesses' => $shown ? [$p['guesses'][0]['value'] ?? null, $p['guesses'][1]['value'] ?? null] : [null, null],
            'price' => $result ? $result['price'] : null,
            'winner' => $result ? self::winner($result) : null,
            'rounds' => self::rounds($p),
            'played' => $p['phase'] === 'done' ? self::playedList($p) : null,
        ];
    }

    public static function adminView(array $p, array $images): array
    {
        $p['images'] = $images;
        $p['rounds'] = self::rounds($p);
        $p['locked'] = [$p['guesses'][0] !== null, $p['guesses'][1] !== null];
        $p['revealAt'] = $p['lockedAt'] ? $p['lockedAt'] + self::REVEAL_DELAY : null;
        $p['winners'] = array_map(fn ($r) => self::winner($r), $p['results']);
        $p['image'] = $p['item'] >= 0 && ($images[$p['item']] ?? false) ? self::imageUrl($p['item']) : null;

        return $p;
    }

    public static function imageVisible(array $p, int $item): bool
    {
        return $p['phase'] !== 'intro' && $item <= $p['item'];
    }

    /**
     * Preis aus Freitext: "1.169", "1169,00 €", "430 Mio", "7,4 Mrd", "7.463.000.000", "59", "1,5k".
     * Deutsche Schreibweise (Punkt = Tausender, Komma = Dezimal), "1.5 mio" klappt aber auch.
     */
    public static function parseAmount(string $text): ?float
    {
        $t = mb_strtolower(trim($text));
        $t = str_replace(['€', 'euro', 'eur', ' ', "\u{00a0}", "'"], '', $t);
        $factor = 1;
        if (preg_match('/(mrd\.?|milliarden?|bn)$/u', $t, $m)) {
            $factor = 1e9;
        } elseif (preg_match('/(mio\.?|millionen?|mill?\.?|m)$/u', $t, $m)) {
            $factor = 1e6;
        } elseif (preg_match('/(tsd\.?|tausend|k)$/u', $t, $m)) {
            $factor = 1e3;
        }
        if ($factor > 1) {
            $t = mb_substr($t, 0, mb_strlen($t) - mb_strlen($m[1]));
        }
        if (!preg_match('/^\d[\d.,]*$/', $t)) {
            return null;
        }

        $lastDot = strrpos($t, '.');
        $lastComma = strrpos($t, ',');
        // Mit Mio/Mrd/k ist ein einzelnes Trennzeichen immer das Komma: "1,500 Mio" = 1,5 Mio
        $single = substr_count($t, '.') + substr_count($t, ',') === 1;
        if ($factor > 1 && $single) {
            $t = str_replace(',', '.', $t);
        } elseif ($lastDot !== false && $lastComma !== false) {
            // Beides: das letzte Zeichen ist das Dezimaltrennzeichen
            $dec = $lastDot > $lastComma ? '.' : ',';
            $t = str_replace($dec === '.' ? ',' : '.', '', $t);
            $t = str_replace($dec, '.', $t);
        } elseif ($lastComma !== false) {
            // Nur Komma: "1,5" = Dezimal, "1,500,000" = Tausender
            $t = substr_count($t, ',') === 1 && strlen($t) - $lastComma - 1 !== 3 ? str_replace(',', '.', $t) : str_replace(',', '', $t);
        } elseif ($lastDot !== false) {
            // Nur Punkt: "1.169" / "7.463.000" = Tausender, "7.4" = Dezimal
            $t = substr_count($t, '.') === 1 && strlen($t) - $lastDot - 1 !== 3 ? $t : str_replace('.', '', $t);
        }
        if (!is_numeric($t)) {
            return null;
        }

        $value = round((float) $t * $factor, 2);

        return $value >= 0 && $value < 1e15 ? $value : null;
    }

    /** Wer hat die Runde gewonnen? [bool, bool] – näher dran gewinnt, gleich nah: beide. */
    private static function winner(array $r): array
    {
        if ($r['override'] !== null) {
            return match ($r['override']) {
                '0' => [true, false], '1' => [false, true], 'both' => [true, true], default => [false, false],
            };
        }
        [$a, $b] = $r['guesses'];
        if ($a === null && $b === null) {
            return [false, false];
        }
        if ($a === null || $b === null) {
            return [$a !== null, $b !== null];
        }
        $da = abs($a - $r['price']);
        $db = abs($b - $r['price']);

        return [$da <= $db, $db <= $da];
    }

    private static function rounds(array $p): array
    {
        $n = [0, 0];
        foreach ($p['results'] as $r) {
            foreach (self::winner($r) as $t => $won) {
                $n[$t] += $won ? 1 : 0;
            }
        }

        return $n;
    }

    private static function playedList(array $p): array
    {
        $out = [];
        foreach ($p['results'] as $i => $r) {
            $out[] = ['name' => $p['items'][$i]['name'] ?? '?', 'price' => $r['price'], 'guesses' => $r['guesses'], 'winner' => self::winner($r)];
        }

        return $out;
    }

    private static function imageUrl(int $item): string
    {
        return sprintf('/lan-night/price-image/%d', $item);
    }

    private static function bothIn(array $p): bool
    {
        return $p['guesses'][0] !== null && $p['guesses'][1] !== null;
    }

    private static function bookWin(array $s): array
    {
        $s = self::unbookWin($s);
        [$a, $b] = self::rounds($s['price']);
        $points = $s['price']['points'];
        if ($points < 1 || ($a === 0 && $b === 0)) {
            return $s;
        }
        $book = function (int $team, int $delta, string $reason) use (&$s) {
            $s['log'][] = ['id' => bin2hex(random_bytes(6)), 'at' => microtime(true), 'team' => $team, 'delta' => $delta, 'reason' => $reason, 'game' => self::GAME];
        };
        if ($a === $b) {
            $book(0, intdiv($points + 1, 2), sprintf('Guess the Price (Unentschieden %d:%d)', $a, $b));
            $book(1, intdiv($points + 1, 2), sprintf('Guess the Price (Unentschieden %d:%d)', $a, $b));
        } else {
            $book($a > $b ? 0 : 1, $points, sprintf('Guess the Price (%d:%d)', $a, $b));
        }

        return $s;
    }

    private static function unbookWin(array $s): array
    {
        $s['log'] = array_values(array_filter($s['log'], fn ($e) => ($e['game'] ?? null) !== self::GAME));

        return $s;
    }

    /** Ein Gegenstand pro Zeile: "Name | Preis" (der Preis steht hinter dem letzten "|"). */
    private static function parseItems(string $source): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $source) as $n => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $cut = strrpos($line, '|');
            $price = $cut === false ? null : self::parseAmount(substr($line, $cut + 1));
            $name = $cut === false ? '' : trim(substr($line, 0, $cut));
            if ($price === null || $name === '') {
                throw new \InvalidArgumentException(sprintf('Zeile %d: bitte als „Name | Preis“ schreiben', $n + 1));
            }
            // Ein weiteres "|" im Namen wird zum Untertitel, z. B. "Karambit | Case Hardened (Factory New)"
            $out[] = ['name' => mb_substr(str_replace(' | ', ' · ', $name), 0, 80), 'price' => $price];
        }

        return $out;
    }
}
