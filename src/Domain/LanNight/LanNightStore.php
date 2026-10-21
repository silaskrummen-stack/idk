<?php

namespace App\Domain\LanNight;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Spielstand der LAN-Night als JSON-Datei (var/lan-night/state.json) mit Datei-Lock.
 *
 * Punkte werden nicht direkt gespeichert, sondern aus dem Verlauf ("log") berechnet –
 * so lässt sich jede Buchung einzeln rückgängig machen, und ein wieder geöffnetes Spiel
 * nimmt seine Punkte automatisch mit.
 */
final class LanNightStore
{
    public const SCREENS = ['title', 'scoreboard', 'game', 'pause', 'quiz', 'guess', 'price', 'awards', 'finale'];
    private const IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];

    private string $dir;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/lan-night')] string $dir,
        #[Autowire('%kernel.project_dir%/lan-night-media/guess-the-game')] private readonly string $guessDir,
        #[Autowire('%kernel.project_dir%/lan-night-media/guess-the-price')] private readonly string $priceDir,
    ) {
        $this->dir = $dir;
    }

    /**
     * Bilder für "Guess the Game", Spiel $game (0-basiert) → Ordner <Nr>/, sortiert nach Dateiname, höchstens 5.
     * Liegt außerhalb von public/, damit niemand über Dateinamen oder URLs spicken kann.
     *
     * @return string[] absolute Pfade
     */
    public function guessImages(int $game): array
    {
        $files = array_filter(
            glob($this->guessDir . '/' . ($game + 1) . '/*') ?: [],
            fn ($f) => is_file($f) && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), self::IMAGE_TYPES, true),
        );
        natcasesort($files);

        return array_slice(array_values($files), 0, LanNightGuess::MAX_PICTURES);
    }

    /** Bild für "Guess the Price", Gegenstand $item (0-basiert) → Datei <Nr>.png / .jpg / … */
    public function priceImage(int $item): ?string
    {
        foreach (glob($this->priceDir . '/' . ($item + 1) . '.*') ?: [] as $file) {
            if (is_file($file) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::IMAGE_TYPES, true)) {
                return $file;
            }
        }

        return null;
    }

    /** @return bool[] gibt es ein Bild pro Gegenstand? */
    public function priceImages(array $state): array
    {
        return array_map(fn ($i) => $this->priceImage($i) !== null, array_keys($state['price']['items']));
    }

    /** @return int[] Anzahl Bilder pro Spiel */
    public function guessCounts(array $state): array
    {
        return array_map(fn ($i) => count($this->guessImages($i)), array_keys($state['guess']['games']));
    }

    public function read(): array
    {
        $file = $this->file();
        if (!is_file($file)) {
            return $this->update(fn (array $s) => $s);
        }
        $fh = fopen($file, 'r');
        flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        $state = self::normalize(json_decode($raw ?: '', true) ?: self::defaultState());

        // Quiz / Guess the Game müssen von selbst weiter (Zeit um, beide Teams drin, …) → einmal schreibend
        if (LanNightQuiz::needsAdvance($state) || LanNightGuess::needsAdvance($state) || LanNightPrice::needsAdvance($state)) {
            return $this->update(fn (array $s) => $s);
        }

        return $this->withScores($state);
    }

    /** Liest, ändert und schreibt den Stand unter exklusivem Lock. */
    public function update(callable $change): array
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
        $fh = fopen($this->file(), 'c+');
        flock($fh, LOCK_EX);
        try {
            $state = json_decode(stream_get_contents($fh) ?: '', true);
            $state = is_array($state) ? $state : self::defaultState();
            $state = LanNightQuiz::advance(self::normalize($state));
            $state = LanNightPrice::advance(LanNightGuess::advance($state, $this->guessCounts($state)));
            $state = self::normalize($change($state));
            // Nach der Änderung nochmal: z. B. haben jetzt beide Teams getippt → sofort zeigen
            $state = LanNightPrice::advance(LanNightGuess::advance($state, $this->guessCounts($state)));
            $state['updatedAt'] = microtime(true);
            unset($state['scores']);

            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            fflush($fh);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }

        return $this->withScores($state);
    }

    /** Der Bildschirm meldet sich bei jeder Abfrage – so sieht der Admin, ob er verbunden ist. */
    public function pingDisplay(): void
    {
        if (is_dir($this->dir)) {
            @file_put_contents($this->dir . '/display.json', json_encode(['at' => microtime(true)]));
        }
    }

    public function displaySeenAt(): ?float
    {
        $file = $this->dir . '/display.json';
        if (!is_file($file)) {
            return null;
        }

        return json_decode((string) file_get_contents($file), true)['at'] ?? null;
    }

    public function apply(string $action, array $in): array
    {
        return $this->update(fn (array $s) => $this->change($s, $action, $in));
    }

    /** Quiz-Stimme vom Handy (öffentlich). */
    public function vote(array $in): array
    {
        return $this->update(fn (array $s) => LanNightQuiz::vote($s, $in));
    }

    /** Award-Stimme vom Handy (öffentlich). */
    public function awardVote(array $in): array
    {
        return $this->update(fn (array $s) => LanNightAwards::vote($s, $in));
    }

    /** Tipp für "Guess the Game" vom Handy (öffentlich). */
    public function guessSubmit(array $in): array
    {
        return $this->update(fn (array $s) => LanNightGuess::submit($s, $in));
    }

    /** Preis-Tipp für "Guess the Price" vom Handy (öffentlich). */
    public function priceSubmit(array $in): array
    {
        return $this->update(fn (array $s) => LanNightPrice::submit($s, $in));
    }

    /** Ältere Spielstände um Quiz, Guess the Game, Guess the Price und Awards ergänzen. */
    private static function normalize(array $s): array
    {
        return LanNightAwards::normalize(LanNightPrice::normalize(LanNightGuess::normalize(LanNightQuiz::normalize($s))));
    }

    private function change(array $s, string $action, array $in): array
    {
        if (str_starts_with($action, 'quiz.')) {
            return LanNightQuiz::apply($s, $action, $in);
        }
        if (str_starts_with($action, 'awards.')) {
            return LanNightAwards::apply($s, $action, $in);
        }
        if (str_starts_with($action, 'guess.')) {
            return LanNightGuess::apply($s, $action, $in, $this->guessCounts($s));
        }
        if (str_starts_with($action, 'price.')) {
            return LanNightPrice::apply($s, $action, $in, $this->priceImages($s));
        }

        switch ($action) {
            case 'screen':
                $screen = (string) ($in['screen'] ?? '');
                if (!in_array($screen, self::SCREENS, true)) {
                    throw new \InvalidArgumentException('Unbekannter Bildschirm');
                }
                $s['screen'] = $screen;
                if ($screen === 'finale') {
                    $s['finaleAt'] = microtime(true);
                }
                break;

            case 'points':
                $team = $this->team($in);
                $delta = (int) ($in['delta'] ?? 0);
                if ($delta === 0) {
                    throw new \InvalidArgumentException('0 Punkte ändern nichts');
                }
                $reason = $this->text($in['reason'] ?? '', 80);
                $s = $this->book($s, $team, $delta, $reason);
                $s['flash'] = ['id' => $this->id(), 'at' => microtime(true), 'kind' => 'points', 'team' => $team, 'delta' => $delta, 'text' => $reason];
                break;

            case 'undo':
                $id = (string) ($in['id'] ?? '');
                $s['log'] = array_values(array_filter($s['log'], fn ($e) => $e['id'] !== $id));
                break;

            case 'team':
                $team = $this->team($in);
                $s['teams'][$team]['name'] = $this->text($in['name'] ?? '', 30) ?: $s['teams'][$team]['name'];
                $s['teams'][$team]['color'] = $this->color($in['color'] ?? '') ?? $s['teams'][$team]['color'];
                $s['teams'][$team]['members'] = array_values(array_filter(array_map(
                    fn ($m) => $this->text($m, 30),
                    preg_split('/[\n,]+/', (string) ($in['members'] ?? ''))
                )));
                break;

            case 'settings':
                $s['title'] = $this->text($in['title'] ?? '', 40) ?: 'LAN Night';
                $s['subtitle'] = $this->text($in['subtitle'] ?? '', 80);
                break;

            case 'game.add':
                $name = $this->text($in['name'] ?? '', 50);
                if ($name === '') {
                    throw new \InvalidArgumentException('Name fehlt');
                }
                $s['games'][] = [
                    'id' => $this->id(),
                    'name' => $name,
                    'description' => $this->text($in['description'] ?? '', 300),
                    'points' => max(1, (int) ($in['points'] ?? 3)),
                    'time' => $this->time($in['time'] ?? ''),
                    'status' => 'open',
                    'result' => null,
                ];
                break;

            case 'game.update':
                $i = $this->game($s, $in);
                $s['games'][$i]['name'] = $this->text($in['name'] ?? '', 50) ?: $s['games'][$i]['name'];
                $s['games'][$i]['description'] = $this->text($in['description'] ?? '', 300);
                $s['games'][$i]['points'] = max(1, (int) ($in['points'] ?? $s['games'][$i]['points']));
                $s['games'][$i]['time'] = $this->time($in['time'] ?? '');
                break;

            case 'game.delete':
                $i = $this->game($s, $in);
                $id = $s['games'][$i]['id'];
                $s = $this->unbookGame($s, $id);
                array_splice($s['games'], $i, 1);
                if ($s['currentGame'] === $id) {
                    $s['currentGame'] = null;
                }
                break;

            case 'game.reorder':
                // Neue Reihenfolge per Drag & Drop: muss genau die vorhandenen Spiele enthalten
                $ids = $in['ids'] ?? null;
                $byId = array_column($s['games'], null, 'id');
                if (!is_array($ids) || count($ids) !== count($byId) || array_diff(array_keys($byId), $ids)) {
                    throw new \InvalidArgumentException('Reihenfolge passt nicht mehr – bitte neu laden');
                }
                $s['games'] = array_map(fn ($id) => $byId[$id], array_values($ids));
                break;

            case 'game.start':
                $i = $this->game($s, $in);
                foreach ($s['games'] as &$g) {
                    if ($g['status'] === 'running') {
                        $g['status'] = 'open';
                    }
                }
                unset($g);
                $s['games'][$i]['status'] = 'running';
                $s['currentGame'] = $s['games'][$i]['id'];
                $s['screen'] = 'game';
                break;

            case 'game.finish':
                $i = $this->game($s, $in);
                $g = $s['games'][$i];
                $result = $in['result'] ?? null;
                if (!in_array($result, [0, 1, 'draw'], true)) {
                    throw new \InvalidArgumentException('Ergebnis fehlt');
                }
                $s = $this->unbookGame($s, $g['id']);
                if ($result === 'draw') {
                    $half = intdiv($g['points'] + 1, 2);
                    $s = $this->book($s, 0, $half, $g['name'] . ' (Unentschieden)', $g['id']);
                    $s = $this->book($s, 1, $half, $g['name'] . ' (Unentschieden)', $g['id']);
                } else {
                    $s = $this->book($s, $result, $g['points'], $g['name'], $g['id']);
                }
                $s['games'][$i]['status'] = 'done';
                $s['games'][$i]['result'] = $result;
                if ($s['currentGame'] === $g['id']) {
                    $s['currentGame'] = null;
                }
                $s['screen'] = 'scoreboard';
                $s['flash'] = ['id' => $this->id(), 'at' => microtime(true), 'kind' => 'result', 'team' => $result, 'delta' => $result === 'draw' ? intdiv($g['points'] + 1, 2) : $g['points'], 'text' => $g['name']];
                break;

            case 'game.reopen':
                $i = $this->game($s, $in);
                $s = $this->unbookGame($s, $s['games'][$i]['id']);
                $s['games'][$i]['status'] = 'open';
                $s['games'][$i]['result'] = null;
                break;

            case 'timer.start':
                $seconds = (int) ($in['seconds'] ?? 0);
                if ($seconds < 1 || $seconds > 6 * 3600) {
                    throw new \InvalidArgumentException('Ungültige Zeit');
                }
                $s['timer'] = ['endsAt' => microtime(true) + $seconds, 'seconds' => $seconds, 'label' => $this->text($in['label'] ?? '', 40)];
                break;

            case 'timer.stop':
                $s['timer'] = null;
                break;

            case 'announce':
                $text = $this->text($in['text'] ?? '', 200);
                $s['announcement'] = $text === '' ? null : ['id' => $this->id(), 'text' => $text];
                break;

            case 'wheel.spin':
                // Glücksrad: pro Team zufällig ein Spieler. Der Server zieht, damit alle Bildschirme gleich landen.
                $history = $s['wheelHistory'] ?? [[], []];
                $picks = [];
                $jitter = [];
                foreach ([0, 1] as $t) {
                    $members = array_values($s['teams'][$t]['members']);
                    if (!$members) {
                        throw new \InvalidArgumentException(sprintf('%s hat noch keine Spieler (Teams → Spieler eintragen)', $s['teams'][$t]['name']));
                    }
                    // Optional: wer schon gezogen wurde, kommt erst wieder dran, wenn alle einmal dran waren
                    $pool = !empty($in['fresh']) ? array_values(array_diff($members, $history[$t])) : $members;
                    if (!$pool) {
                        $history[$t] = [];
                        $pool = $members;
                    }
                    $name = $pool[random_int(0, count($pool) - 1)];
                    $picks[] = array_search($name, $members, true);
                    $history[$t][] = $name;
                    $jitter[] = random_int(-35, 35) / 100; // wo im Feld der Zeiger stehen bleibt
                }
                $s['wheelHistory'] = $history;
                $s['wheel'] = [
                    'id' => $this->id(),
                    'at' => microtime(true),
                    'members' => [array_values($s['teams'][0]['members']), array_values($s['teams'][1]['members'])],
                    'picks' => $picks,
                    'jitter' => $jitter,
                    'durations' => [6.0, 7.5],
                ];
                break;

            case 'wheel.hide':
                $s['wheel'] = null;
                break;

            case 'wheel.forget':
                $s['wheelHistory'] = [[], []];
                break;

            case 'reset':
                $fresh = self::defaultState();
                $s = LanNightQuiz::apply($s, 'quiz.reset', []);
                $s = LanNightAwards::apply($s, 'awards.reset', []);
                $s = LanNightGuess::apply($s, 'guess.reset', [], []);
                $s = LanNightPrice::apply($s, 'price.reset', [], []);
                $s['log'] = [];
                $s['flash'] = null;
                $s['timer'] = null;
                $s['announcement'] = null;
                $s['wheel'] = null;
                $s['wheelHistory'] = [[], []];
                $s['currentGame'] = null;
                $s['screen'] = 'title';
                foreach ($s['games'] as &$g) {
                    $g['status'] = 'open';
                    $g['result'] = null;
                }
                unset($g);
                if (!empty($in['everything'])) {
                    $s = $fresh;
                }
                break;

            default:
                throw new \InvalidArgumentException('Unbekannte Aktion');
        }

        return $s;
    }

    private function book(array $s, int $team, int $delta, string $reason, ?string $gameId = null): array
    {
        $s['log'][] = ['id' => $this->id(), 'at' => microtime(true), 'team' => $team, 'delta' => $delta, 'reason' => $reason, 'game' => $gameId];

        return $s;
    }

    private function unbookGame(array $s, string $gameId): array
    {
        $s['log'] = array_values(array_filter($s['log'], fn ($e) => ($e['game'] ?? null) !== $gameId));

        return $s;
    }

    private function withScores(array $s): array
    {
        $s['scores'] = [0, 0];
        foreach ($s['log'] as $e) {
            $s['scores'][$e['team']] += $e['delta'];
        }

        return $s;
    }

    private function team(array $in): int
    {
        $team = $in['team'] ?? null;
        if ($team !== 0 && $team !== 1) {
            throw new \InvalidArgumentException('Team fehlt');
        }

        return $team;
    }

    private function game(array $s, array $in): int
    {
        foreach ($s['games'] as $i => $g) {
            if ($g['id'] === ($in['id'] ?? null)) {
                return $i;
            }
        }
        throw new \InvalidArgumentException('Spiel nicht gefunden');
    }

    private function text(mixed $v, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $v)), 0, $max);
    }

    /** Geplanter Start als "HH:MM" (leer = ohne Uhrzeit) */
    private function time(mixed $v): string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $v) ? (string) $v : '';
    }

    private function color(mixed $v): ?string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', (string) $v) ? strtoupper($v) : null;
    }

    private function id(): string
    {
        return bin2hex(random_bytes(6));
    }

    private function file(): string
    {
        return $this->dir . '/state.json';
    }

    public static function defaultState(): array
    {
        $game = fn (string $name, string $description, int $points, string $time) => [
            'id' => bin2hex(random_bytes(6)), 'name' => $name, 'description' => $description,
            'points' => $points, 'time' => $time, 'status' => 'open', 'result' => null,
        ];

        return [
            'title' => 'LAN Night',
            'subtitle' => '',
            'screen' => 'title',
            'teams' => [
                ['name' => 'Team Gelb', 'color' => '#FFCA22', 'members' => []],
                ['name' => 'Team Mint', 'color' => '#5BFEC4', 'members' => []],
            ],
            'games' => [
                $game('Mario Kart', 'Grand Prix – die Platzierungen aller Fahrer werden pro Team addiert.', 3, '19:30'),
                $game('Rocket League', '3 gegen 3, Best of 3.', 3, '20:30'),
                $game('Counter-Strike 2', 'Wingman oder 5v5 – erstes Team mit 9 Runden.', 5, '21:30'),
                $game('Quiz', 'Allgemeinwissen & Gaming-Fragen. Wer zuerst buzzert, antwortet.', 3, '23:00'),
            ],
            'currentGame' => null,
            'log' => [],
            'flash' => null,
            'timer' => null,
            'announcement' => null,
            'wheel' => null,
            'wheelHistory' => [[], []],
            'finaleAt' => null,
            'updatedAt' => microtime(true),
        ];
    }
}
