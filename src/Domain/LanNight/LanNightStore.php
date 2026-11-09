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
    public const SCREENS = ['title', 'scoreboard', 'pick', 'game', 'pause', 'quiz', 'guess', 'price', 'awards', 'finale'];
    /** Spiele, deren Punkte aus einem eigenen Segment kommen (Quiz, Guess the Game, Guess the Price) */
    public const SEGMENTS = ['quiz', 'guess', 'price'];
    private const MAX_GAMES = 60;
    private const IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
    private const AUDIO_TYPES = ['mp3', 'ogg', 'wav', 'm4a'];

    private string $dir;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/lan-night')] string $dir,
        #[Autowire('%kernel.project_dir%/lan-night-media/guess-the-game')] private readonly string $guessDir,
        #[Autowire('%kernel.project_dir%/lan-night-media/guess-the-price')] private readonly string $priceDir,
        #[Autowire('%kernel.project_dir%/lan-night-media/cameos')] private readonly string $cameoDir,
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

    /** Bild einer Gastauftritt-Figur: erste Bilddatei in cameos/<figur>/ */
    public function cameoImage(string $who): ?string
    {
        return $this->cameoFiles($who, self::IMAGE_TYPES)[0] ?? null;
    }

    /** @return string[] Sätze (Audiodateien) einer Figur, nach Dateiname sortiert */
    public function cameoLines(string $who): array
    {
        return array_slice($this->cameoFiles($who, self::AUDIO_TYPES), 0, LanNightCameo::MAX_LINES);
    }

    /** Figuren mit Platz, Anzahl Sätze und Version (Änderungszeit → neuer Cache nach Austausch) */
    public function cameoCast(): array
    {
        $cast = [];
        foreach (LanNightCameo::CAST as $key => $figure) {
            $files = array_merge($this->cameoLines($key), array_filter([$this->cameoImage($key)]));
            $cast[] = $figure + [
                'key' => $key,
                'lines' => count($this->cameoLines($key)),
                'image' => $this->cameoImage($key) !== null,
                'v' => $files ? max(array_map('filemtime', $files)) : 0,
            ];
        }

        return $cast;
    }

    /** @return array<string,int> Anzahl Sätze pro Figur */
    private function cameoLineCounts(): array
    {
        return array_map(fn ($key) => count($this->cameoLines($key)), array_combine(array_keys(LanNightCameo::CAST), array_keys(LanNightCameo::CAST)));
    }

    private function cameoFiles(string $who, array $types): array
    {
        if (!isset(LanNightCameo::CAST[$who])) {
            return [];
        }
        $files = array_filter(
            glob($this->cameoDir . '/' . $who . '/*') ?: [],
            fn ($f) => is_file($f) && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $types, true),
        );
        natcasesort($files);

        return array_values($files);
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
        $s = LanNightCameo::normalize(LanNightAwards::normalize(LanNightPrice::normalize(LanNightGuess::normalize(LanNightQuiz::normalize($s)))));
        $s['pickOverride'] ??= null;
        $s['target'] ??= 40;   // Siegbedingung: wer zuerst so viele Punkte hat
        $s['slots'] ??= null;
        $s['lineup'] ??= null;
        foreach ($s['games'] as &$g) {
            $g['players'] ??= null;   // Spieler gesamt (beide Teams zusammen), null = alle
            // Ältere Spielstände: Segment am Namen erkennen ("Trivia Quiz" → Quiz, …)
            if (!array_key_exists('segment', $g)) {
                $g['segment'] = self::segmentOf($g['name']);
            }
            // Die Spieleliste bestimmt, was der Sieg im Segment wert ist
            if ($g['segment']) {
                $s[$g['segment']]['points'] = min(50, max(1, (int) $g['points']));
            }
        }
        unset($g);

        return $s;
    }

    /** "Trivia Quiz" → quiz, "Guess the Game" → guess, "Guess the Price" → price, sonst null */
    private static function segmentOf(string $name): ?string
    {
        $n = mb_strtolower($name);

        return match (true) {
            str_contains($n, 'guess the game') || str_contains($n, 'errate das spiel') => 'guess',
            str_contains($n, 'guess the price') || str_contains($n, 'errate den preis') => 'price',
            str_contains($n, 'quiz') => 'quiz',
            default => null,
        };
    }

    private function change(array $s, string $action, array $in): array
    {
        if (str_starts_with($action, 'quiz.')) {
            return self::pointsFromSegment(LanNightQuiz::apply($s, $action, $in), 'quiz');
        }
        if (str_starts_with($action, 'awards.')) {
            return LanNightAwards::apply($s, $action, $in);
        }
        if (str_starts_with($action, 'guess.')) {
            return self::pointsFromSegment(LanNightGuess::apply($s, $action, $in, $this->guessCounts($s)), 'guess');
        }
        if (str_starts_with($action, 'price.')) {
            return self::pointsFromSegment(LanNightPrice::apply($s, $action, $in, $this->priceImages($s)), 'price');
        }
        if (str_starts_with($action, 'cameo.')) {
            return LanNightCameo::apply($s, $action, $in, $this->cameoLineCounts());
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
                if (isset($in['target'])) {
                    $s['target'] = min(999, max(1, (int) $in['target']));
                }
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
                    'segment' => self::segmentOf($name),
                    'players' => $this->players($in['players'] ?? null),
                ];
                break;

            case 'games.import':
                // Ganze Liste auf einmal: eine Zeile pro Spiel, Punkte hinten ("Wreckfest 4", "Wreckfest; 4").
                // Gleichnamige Spiele behalten Ergebnis und Beschreibung, fehlende fliegen raus (samt ihren Punkten).
                $lines = array_filter(array_map('trim', preg_split('/\R/u', (string) ($in['source'] ?? ''))));
                if (!$lines) {
                    throw new \InvalidArgumentException('Liste ist leer');
                }
                if (count($lines) > self::MAX_GAMES) {
                    throw new \InvalidArgumentException(sprintf('Höchstens %d Spiele', self::MAX_GAMES));
                }
                $old = [];
                foreach ($s['games'] as $g) {
                    $old[mb_strtolower($g['name'])] = $g;
                }
                $games = [];
                foreach ($lines as $line) {
                    $points = 3;
                    $players = null;
                    // Spielerzahl hinten mit | oder / ("Wreckfest 4 | 16") oder als "16 Spieler"
                    if (preg_match('/^(.*?)(?:\s*[|\/]\s*|\s+)(\d{1,2})\s*spieler\.?$/iu', $line, $m)
                        || preg_match('/^(.*?)\s*[|\/]\s*(\d{1,2})\.?$/u', $line, $m)) {
                        [$line, $players] = [trim($m[1]), $this->players($m[2])];
                    }
                    if (preg_match('/^(.*?)[\s;|:,\-–]+(\d{1,2})\s*(p|pkt|punkte?)?\.?$/iu', $line, $m) && trim($m[1]) !== '') {
                        [$line, $points] = [trim($m[1]), max(1, (int) $m[2])];
                    }
                    $name = $this->text($line, 50);
                    $key = mb_strtolower($name);
                    if (isset($games[$key])) {
                        continue;
                    }
                    $g = $old[$key] ?? ['id' => $this->id(), 'name' => $name, 'description' => '', 'time' => '', 'status' => 'open', 'result' => null];
                    $g['points'] = $points;
                    $g['players'] = $players;
                    $g['segment'] = self::segmentOf($name);
                    $games[$key] = $g;
                    unset($old[$key]);
                }
                foreach ($old as $gone) {
                    $s = $this->unbookGame($s, $gone['id']);
                    if ($s['currentGame'] === $gone['id']) {
                        $s['currentGame'] = null;
                    }
                }
                $s['games'] = array_values($games);
                break;

            case 'pick.team':
                // Wer wählt das nächste Spiel? Standard: Verlierer des letzten Spiels. Hier von Hand überschreiben.
                $team = $in['team'] ?? null;
                if ($team === 'auto') {
                    $s['pickOverride'] = null;
                } else {
                    $s['pickOverride'] = ['team' => $this->team($in), 'at' => microtime(true)];
                }
                break;

            case 'game.update':
                $i = $this->game($s, $in);
                $s['games'][$i]['name'] = $this->text($in['name'] ?? '', 50) ?: $s['games'][$i]['name'];
                $s['games'][$i]['description'] = $this->text($in['description'] ?? '', 300);
                $s['games'][$i]['points'] = max(1, (int) ($in['points'] ?? $s['games'][$i]['points']));
                $s['games'][$i]['time'] = $this->time($in['time'] ?? '');
                $s['games'][$i]['segment'] = self::segmentOf($s['games'][$i]['name']);
                $s['games'][$i]['players'] = $this->players($in['players'] ?? null);
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
                $s = $this->startGame($s, $this->game($s, $in));
                break;

            case 'game.finish':
                $i = $this->game($s, $in);
                $g = $s['games'][$i];
                $this->notSegment($g);
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
                // Nach jedem Spiel: Verlierer wählt das nächste
                $s['screen'] = 'pick';
                $s['flash'] = ['id' => $this->id(), 'at' => microtime(true), 'kind' => 'result', 'team' => $result, 'delta' => $result === 'draw' ? intdiv($g['points'] + 1, 2) : $g['points'], 'text' => $g['name']];
                break;

            case 'game.reopen':
                $i = $this->game($s, $in);
                $this->notSegment($s['games'][$i]);
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
                $members = [array_values($s['teams'][0]['members']), array_values($s['teams'][1]['members'])];
                $game = isset($in['game']) ? $s['games'][$this->game($s, ['id' => $in['game']])] : null;
                $s['wheel'] = [
                    'id' => $this->id(),
                    'at' => microtime(true),
                    'members' => $members,
                    'picks' => $picks,
                    'jitter' => $jitter,
                    'durations' => [6.0, 7.5],
                    'game' => $game['name'] ?? null,
                ];
                $s['slots'] = null;
                if ($game) {
                    $s['lineup'] = ['gameId' => $game['id'], 'teams' => [[$members[0][$picks[0]]], [$members[1][$picks[1]]]]];
                    // Ausgelost = dieses Spiel ist dran: läuft der Bildschirm danach direkt aufs Spiel (mit Aufstellung)
                    $s = $this->startGame($s, $this->game($s, ['id' => $game['id']]));
                }
                break;

            case 'slots.spin':
                // Spielautomat: pro Team (Spieler gesamt ÷ 2) Leute, Walze für Walze. Der Server zieht – alle Bildschirme gleich.
                $game = $s['games'][$this->game($s, $in)];
                $perTeam = intdiv((int) ($game['players'] ?? 0), 2);
                if ($perTeam < 2) {
                    throw new \InvalidArgumentException($perTeam === 1 ? '1 pro Team – dafür das Glücksrad nehmen' : sprintf('Bei „%s“ ist keine Spielerzahl eingetragen', $game['name']));
                }
                $history = $s['wheelHistory'] ?? [[], []];
                $members = [];
                $picks = [];
                foreach ([0, 1] as $t) {
                    $members[$t] = array_values($s['teams'][$t]['members']);
                    if (!$members[$t]) {
                        throw new \InvalidArgumentException(sprintf('%s hat noch keine Spieler (Teams → Spieler eintragen)', $s['teams'][$t]['name']));
                    }
                    [$names, $history[$t]] = $this->draw($members[$t], $perTeam, $history[$t], !empty($in['fresh']));
                    $picks[$t] = array_map(fn ($n) => array_search($n, $members[$t], true), $names);
                }
                $s['wheelHistory'] = $history;
                $s['wheel'] = null;
                $s['slots'] = [
                    'id' => $this->id(),
                    'at' => microtime(true),
                    'game' => $game['name'],
                    'perTeam' => $perTeam,
                    'members' => $members,
                    'picks' => $picks,
                ];
                $s['lineup'] = [
                    'gameId' => $game['id'],
                    'teams' => [array_map(fn ($i) => $members[0][$i], $picks[0]), array_map(fn ($i) => $members[1][$i], $picks[1])],
                ];
                $s = $this->startGame($s, $this->game($s, $in));
                break;

            case 'slots.hide':
                $s['slots'] = null;
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
                $s['cameo'] = null;
                $s['pickOverride'] = null;
                $s['slots'] = null;
                $s['lineup'] = null;
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

        // Segment-Spiele: Punkte und Ergebnis kommen aus Quiz / Guess the Game / Guess the Price
        foreach ($s['games'] as &$g) {
            if (!($g['segment'] ?? null)) {
                continue;
            }
            $won = self::deltas($s, $g['segment']);
            if ($won !== null) {
                $g['status'] = 'done';
                $g['result'] = $won[0] === $won[1] ? 'draw' : ($won[0] > $won[1] ? 0 : 1);
            } elseif ($g['status'] === 'done') {
                $g['status'] = 'open';
                $g['result'] = null;
            }
        }
        unset($g);

        $s['picker'] = self::picker($s);

        return $s;
    }

    /** @return int[]|null Punkte pro Team für ein Spiel (Spiel-ID oder Segment), null = noch nichts gebucht */
    private static function deltas(array $s, string $game): ?array
    {
        $d = null;
        foreach ($s['log'] as $e) {
            if (($e['game'] ?? null) === $game) {
                $d ??= [0, 0];
                $d[$e['team']] += $e['delta'];
            }
        }

        return $d;
    }

    /**
     * Wer wählt das nächste Spiel?
     *  - von Hand gesetzt (nach dem letzten Ergebnis) → das gilt
     *  - letztes Spiel hatte einen Sieger → der Verlierer
     *  - Unentschieden / noch nichts gespielt → das Team, das hinten liegt (Gleichstand: offen)
     *
     * @return array{team: int|null, why: string, game: string|null}
     */
    private static function picker(array $s): array
    {
        $last = null;
        foreach ($s['log'] as $e) {
            if (($e['game'] ?? null) !== null && (!$last || $e['at'] >= $last['at'])) {
                $last = $e;
            }
        }
        $name = null;
        if ($last) {
            foreach ($s['games'] as $g) {
                if ($g['id'] === $last['game'] || ($g['segment'] ?? null) === $last['game']) {
                    $name = $g['name'];
                }
            }
        }

        $o = $s['pickOverride'] ?? null;
        if ($o && (!$last || $o['at'] > $last['at'])) {
            return ['team' => $o['team'], 'why' => 'manual', 'game' => $name];
        }
        if ($last) {
            $d = self::deltas($s, $last['game']);
            if ($d[0] !== $d[1]) {
                return ['team' => $d[0] > $d[1] ? 1 : 0, 'why' => 'lost', 'game' => $name];
            }
        }
        if ($s['scores'][0] !== $s['scores'][1]) {
            return ['team' => $s['scores'][0] < $s['scores'][1] ? 0 : 1, 'why' => 'behind', 'game' => $name];
        }

        return ['team' => null, 'why' => $last ? 'tie' : 'first', 'game' => $name];
    }

    /** Punkte im Segment-Reiter geändert → in die Spieleliste übernehmen (die gilt danach wieder für beide) */
    private static function pointsFromSegment(array $s, string $segment): array
    {
        foreach ($s['games'] as &$g) {
            if (($g['segment'] ?? null) === $segment) {
                $g['points'] = max(1, (int) $s[$segment]['points']);
            }
        }
        unset($g);

        return $s;
    }

    private function notSegment(array $g): void
    {
        if ($g['segment'] ?? null) {
            $label = ['quiz' => 'Quiz', 'guess' => 'Guess the Game', 'price' => 'Guess the Price'][$g['segment']];
            throw new \InvalidArgumentException(sprintf('Die Punkte für „%s“ kommen aus dem %s-Reiter', $g['name'], $label));
        }
    }

    /** Spiel starten (läuft schon / schon fertig → nur auf den Bildschirm wechseln) */
    private function startGame(array $s, int $i): array
    {
        if ($s['games'][$i]['status'] === 'open') {
            foreach ($s['games'] as &$g) {
                if ($g['status'] === 'running') {
                    $g['status'] = 'open';
                }
            }
            unset($g);
            $s['games'][$i]['status'] = 'running';
        }
        $s['currentGame'] = $s['games'][$i]['id'];
        // Quiz / Guess the Game / Guess the Price laufen auf ihrem eigenen Bildschirm
        $s['screen'] = $s['games'][$i]['segment'] ?? 'game';

        return $s;
    }

    /**
     * Zieht $count verschiedene Spieler. Mit $fresh kommen zuerst die dran, die seit dem letzten Durchgang
     * noch nicht gezogen wurden; reichen die nicht, beginnt ein neuer Durchgang.
     *
     * @return array{string[], string[]} [gezogene Namen, neuer Verlauf]
     */
    private function draw(array $members, int $count, array $history, bool $fresh): array
    {
        $count = min($count, count($members));
        $shuffle = function (array $a): array {
            for ($i = count($a) - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
            }

            return $a;
        };
        if (!$fresh) {
            $names = array_slice($shuffle($members), 0, $count);

            return [$names, array_values(array_unique(array_merge($history, $names)))];
        }
        $unseen = $shuffle(array_values(array_diff($members, $history)));
        if (count($unseen) >= $count) {
            $names = array_slice($unseen, 0, $count);

            return [$names, array_merge($history, $names)];
        }
        // Rest des Durchgangs + Auffüllen aus allen anderen → neuer Durchgang beginnt mit genau diesen
        $names = array_merge($unseen, array_slice($shuffle(array_values(array_diff($members, $unseen))), 0, $count - count($unseen)));

        return [$names, $names];
    }

    /** Spieler gesamt für ein Spiel: 2–64, leer = alle */
    private function players(mixed $v): ?int
    {
        $n = (int) $v;

        return $n >= 2 ? min(64, $n) : null;
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

    private const DEFAULT_GAMES = [
        ['Wreckfest', 4], ['Trivia Quiz', 2], ['Call of Duty 2', 4], ['Battlefield 2142', 4], ['Fling to the Finish', 3],
        ['Guess the Game', 2], ['Guess the Price', 2], ['Overwatch 2', 4], ['FlatOut 2', 3], ['Star Wars: Empire at War', 3],
        ['Rainbow Six Siege', 3], ['Counter-Strike 2', 3], ['Teeworlds', 3], ['SWAT 4', 3], ['Stronghold Crusader', 3],
        ['Carrier Command 2', 3], ['Mario Kart', 3], ['Left 4 Dead 2', 3], ['Golf With Your Friends', 3], ['Crab Game', 3],
        ['Stumble Guys', 3], ['Fistful of Frags', 3],
    ];

    public static function defaultState(): array
    {
        $game = fn (string $name, string $description, int $points, string $time) => [
            'id' => bin2hex(random_bytes(6)), 'name' => $name, 'description' => $description,
            'points' => $points, 'time' => $time, 'status' => 'open', 'result' => null, 'segment' => self::segmentOf($name),
        ];

        return [
            'title' => 'LAN Night',
            'target' => 40,
            'subtitle' => '',
            'screen' => 'title',
            'teams' => [
                ['name' => 'Team Gold', 'color' => '#FFCA22', 'members' => []],
                ['name' => 'Team Mint', 'color' => '#5BFEC4', 'members' => []],
            ],
            // Kein fester Zeitplan: der Verlierer des letzten Spiels wählt das nächste (Bildschirm "Spielauswahl")
            'games' => array_map(fn ($g) => $game($g[0], '', $g[1], ''), self::DEFAULT_GAMES),
            'currentGame' => null,
            'log' => [],
            'flash' => null,
            'timer' => null,
            'announcement' => null,
            'wheel' => null,
            'wheelHistory' => [[], []],
            'pickOverride' => null,
            'finaleAt' => null,
            'updatedAt' => microtime(true),
        ];
    }
}
