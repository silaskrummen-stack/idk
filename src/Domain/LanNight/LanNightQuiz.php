<?php

namespace App\Domain\LanNight;

/**
 * Trivia-Quiz der LAN-Night.
 *
 * Ablauf (quiz.phase):  intro → question → locked → reveal → question → … → done
 *  - question: Frage steht auf dem Bildschirm, Zeit läuft. Pro Team loggt EIN Handy die Antwort ein
 *              (QR-Code → /lan-night/quiz). Der Bildschirm zeigt nur, WER schon eingeloggt hat.
 *  - locked:   beide Teams eingeloggt oder Zeit um → Bildschirm zeigt, wer was gewählt hat
 *  - reveal:   REVEAL_DELAY Sekunden später automatisch: richtige Antwort leuchtet.
 *              Danach startet die Spielleitung die nächste Frage.
 *  - done:     Ergebnis; das Team mit mehr richtigen Antworten bekommt "points" (Standard 3) gutgeschrieben,
 *              bei Gleichstand beide die Hälfte (aufgerundet) – im Verlauf als game "quiz".
 *
 * Die Spielleitung kann pro Team von Hand eine Antwort setzen (z. B. wenn ein Team ruft) – auch nachträglich.
 * Alles hier arbeitet auf dem Zustand als Array – gespeichert wird im LanNightStore.
 */
final class LanNightQuiz
{
    public const GAME = 'quiz';
    public const REVEAL_DELAY = 3;

    public static function defaults(): array
    {
        $parsed = self::parse(self::DEFAULT_SOURCE);

        return [
            'title' => $parsed['title'],
            'rules' => $parsed['rules'],
            'source' => self::DEFAULT_SOURCE,
            'questions' => $parsed['questions'],
            'seconds' => 30,
            'points' => 3,               // für den Quiz-Sieg (nicht pro Frage)
            'scoring' => 'win',
            'phase' => 'intro',
            'index' => -1,
            'startedAt' => null,
            'endsAt' => null,
            'lockedAt' => null,
            'answers' => [null, null],   // eingeloggte Antwort pro Team (0–3) oder null
            'results' => [],
        ];
    }

    public static function normalize(array $s): array
    {
        $q = $s['quiz'] ?? [];
        // Alte Fassung (Mehrheit der Handys, 15 s) → neue Fassung
        if ($q && !array_key_exists('answers', $q)) {
            $q['seconds'] = 30;
            $q['source'] = str_replace('Pro Frage 15 Sekunden', 'Pro Frage 30 Sekunden', $q['source'] ?? '');
            $q['rules'] = str_replace('Pro Frage 15 Sekunden', 'Pro Frage 30 Sekunden', $q['rules'] ?? '');
            $q['answers'] = $q['teamAnswers'] ?? [null, null];
            unset($q['votes'], $q['manual'], $q['teamAnswers']);
        }
        // Früher gab es Punkte pro Frage – jetzt nur noch für den Quiz-Sieg
        if ($q && ($q['scoring'] ?? null) !== 'win') {
            $q['scoring'] = 'win';
            $q['points'] = 3;
            $s['log'] = array_values(array_filter($s['log'] ?? [], fn ($e) => ($e['game'] ?? null) !== self::GAME));
        }
        $s['quiz'] = array_merge(self::defaults(), $q);

        return $s;
    }

    /** Muss das Quiz von selbst weiter (Zeit um / beide eingeloggt / Auflösung fällig)? */
    public static function needsAdvance(array $s): bool
    {
        $q = $s['quiz'] ?? null;
        if (!$q) {
            return false;
        }
        $now = microtime(true);

        return ($q['phase'] === 'question' && ($now > $q['endsAt'] + 0.5 || self::bothIn($q)))
            || ($q['phase'] === 'locked' && $now >= $q['lockedAt'] + self::REVEAL_DELAY);
    }

    /** Wird bei jedem Lesen/Schreiben geprüft – so läuft das Quiz ohne Klick weiter. */
    public static function advance(array $s): array
    {
        $q = $s['quiz'];
        $now = microtime(true);
        if ($q['phase'] === 'question' && ($now > $q['endsAt'] + 0.5 || self::bothIn($q))) {
            $s = self::lock($s);
        }
        if ($s['quiz']['phase'] === 'locked' && $now >= $s['quiz']['lockedAt'] + self::REVEAL_DELAY) {
            $s = self::reveal($s);
        }

        return $s;
    }

    public static function apply(array $s, string $action, array $in): array
    {
        $q = $s['quiz'];

        switch ($action) {
            case 'quiz.open':
                $s['screen'] = 'quiz';
                if ($q['phase'] === 'done' || $q['index'] < 0) {
                    $q['phase'] = 'intro';
                }
                break;

            case 'quiz.next':
                $next = $q['index'] + 1;
                if (!isset($q['questions'][$next])) {
                    throw new \InvalidArgumentException('Keine weiteren Fragen');
                }
                if (in_array($q['phase'], ['question', 'locked'], true)) {
                    throw new \InvalidArgumentException('Die aktuelle Frage läuft noch');
                }
                if ($q['phase'] === 'done') {
                    $s = self::unbookWin($s); // es geht doch weiter → Sieg-Punkte erst am neuen Ende
                }
                $q['index'] = $next;
                $q['phase'] = 'question';
                $q['startedAt'] = microtime(true);
                $q['endsAt'] = microtime(true) + $q['seconds'];
                $q['lockedAt'] = null;
                $q['answers'] = [null, null];
                $s['screen'] = 'quiz';
                break;

            case 'quiz.lock':
                // Zeit vorzeitig beenden
                if ($q['phase'] !== 'question') {
                    throw new \InvalidArgumentException('Gerade läuft keine Frage');
                }
                $s['quiz'] = $q;
                $s = self::lock($s);
                $q = $s['quiz'];
                break;

            case 'quiz.reveal':
                // Sofort auflösen, ohne die 3 Sekunden abzuwarten
                $s['quiz'] = $q;
                if ($q['phase'] === 'question') {
                    $s = self::lock($s);
                }
                if ($s['quiz']['phase'] !== 'locked') {
                    throw new \InvalidArgumentException('Gerade läuft keine Frage');
                }
                $s = self::reveal($s);
                $q = $s['quiz'];
                break;

            case 'quiz.set':
                // Team-Antwort von Hand setzen oder (null) wieder freigeben
                $team = $in['team'] ?? null;
                $answer = $in['answer'] ?? null;
                if (!in_array($team, [0, 1], true) || !in_array($answer, [null, 0, 1, 2, 3], true)) {
                    throw new \InvalidArgumentException('Ungültige Antwort');
                }
                if (!in_array($q['phase'], ['question', 'locked', 'reveal'], true)) {
                    throw new \InvalidArgumentException('Gerade läuft keine Frage');
                }
                $q['answers'][$team] = $answer;
                if ($q['phase'] === 'reveal') {
                    $s['quiz'] = $q;
                    $s = self::score($s);
                    $q = $s['quiz'];
                }
                break;

            case 'quiz.done':
                if (in_array($q['phase'], ['question', 'locked'], true)) {
                    throw new \InvalidArgumentException('Die aktuelle Frage läuft noch');
                }
                if ($q['index'] < 0) {
                    throw new \InvalidArgumentException('Es wurde noch keine Frage gespielt');
                }
                $q['phase'] = 'done';
                $s['screen'] = 'quiz';
                $s['quiz'] = $q;
                $s = self::bookWin($s);
                break;

            case 'quiz.close':
                if (in_array($q['phase'], ['question', 'locked'], true)) {
                    $q['phase'] = 'intro';
                    $q['index'] = max(-1, $q['index'] - 1); // die offene Frage kommt beim nächsten Mal nochmal
                }
                $s['screen'] = 'scoreboard';
                break;

            case 'quiz.reset':
                $s['log'] = array_values(array_filter($s['log'], fn ($e) => ($e['game'] ?? null) !== self::GAME));
                $config = array_intersect_key($q, array_flip(['title', 'rules', 'source', 'questions', 'seconds', 'points']));
                $q = array_merge(self::defaults(), $config);
                break;

            case 'quiz.config':
                $parsed = self::parse((string) ($in['source'] ?? ''));
                if (in_array($q['phase'], ['question', 'locked'], true)) {
                    throw new \InvalidArgumentException('Nicht während einer Frage ändern');
                }
                $q['source'] = (string) $in['source'];
                $q['title'] = $parsed['title'];
                $q['rules'] = $parsed['rules'];
                $q['questions'] = $parsed['questions'];
                $q['seconds'] = min(300, max(5, (int) ($in['seconds'] ?? $q['seconds'])));
                $q['points'] = min(50, max(0, (int) ($in['points'] ?? $q['points'])));
                break;

            default:
                throw new \InvalidArgumentException('Unbekannte Aktion');
        }
        $s['quiz'] = $q;

        return $s;
    }

    /** Ein Handy loggt die Antwort für sein Team ein (öffentlich, ohne Login). Gilt fürs ganze Team und ist endgültig. */
    public static function vote(array $s, array $in): array
    {
        $q = $s['quiz'];
        $team = $in['team'] ?? null;
        $answer = $in['answer'] ?? null;

        if (!in_array($team, [0, 1], true) || !in_array($answer, [0, 1, 2, 3], true)) {
            throw new \InvalidArgumentException('Ungültige Antwort');
        }
        if ($q['phase'] === 'intro' || $q['phase'] === 'done') {
            throw new \InvalidArgumentException('Gerade läuft keine Frage');
        }
        if ($q['phase'] !== 'question' || ($in['index'] ?? null) !== $q['index']) {
            throw new \InvalidArgumentException('Zu spät – die Frage ist schon vorbei');
        }
        if ($q['answers'][$team] !== null) {
            throw new \InvalidArgumentException('Euer Team hat schon eingeloggt');
        }

        $q['answers'][$team] = $answer;
        $s['quiz'] = $q;

        // Beide Teams drin → sofort zeigen, wer was gewählt hat
        return self::bothIn($q) ? self::lock($s) : $s;
    }

    /** Was Bildschirm und Handys sehen: keine Lösung vor der Auflösung, keine Team-Antworten vor dem Einloggen beider. */
    public static function publicView(array $q): array
    {
        $current = $q['questions'][$q['index']] ?? null;
        $asked = in_array($q['phase'], ['question', 'locked', 'reveal'], true) && $current;
        $shown = in_array($q['phase'], ['locked', 'reveal'], true);

        return [
            'title' => $q['title'],
            'rules' => $q['rules'],
            'phase' => $q['phase'],
            'index' => $q['index'],
            'total' => count($q['questions']),
            'seconds' => $q['seconds'],
            'points' => $q['points'],
            'endsAt' => $q['endsAt'],
            'revealAt' => $q['lockedAt'] ? $q['lockedAt'] + self::REVEAL_DELAY : null,
            'question' => $asked ? ['q' => $current['q'], 'answers' => $current['answers']] : null,
            'correct' => $q['phase'] === 'reveal' ? $current['correct'] : null,
            'locked' => [$q['answers'][0] !== null, $q['answers'][1] !== null],
            'answers' => $shown ? $q['answers'] : [null, null],
            'tally' => self::tally($q['results']),
        ];
    }

    public static function adminView(array $q): array
    {
        $q['locked'] = [$q['answers'][0] !== null, $q['answers'][1] !== null];
        $q['revealAt'] = $q['lockedAt'] ? $q['lockedAt'] + self::REVEAL_DELAY : null;
        $q['tally'] = self::tally($q['results']);
        $q['total'] = count($q['questions']);

        return $q;
    }

    /**
     * Fragen aus dem Text lesen. Format:
     *   TITEL
     *   Regeln: …
     *   Frage 1: …
     *   A) … B) … C) … D) …
     *   Richtige Antwort: D) …
     */
    public static function parse(string $source): array
    {
        $title = '';
        $rules = '';
        $questions = [];
        $cur = null;
        $letters = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3];

        foreach (preg_split('/\R/u', $source) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^Frage\s*\d*\s*[:.)]\s*(.+)$/iu', $line, $m)) {
                if ($cur) {
                    $questions[] = $cur;
                }
                $cur = ['q' => trim($m[1]), 'answers' => [], 'correct' => null];
            } elseif ($cur && preg_match('/^Richtige\s+Antwort\s*:\s*([A-D])\b/iu', $line, $m)) {
                $cur['correct'] = $letters[strtoupper($m[1])];
            } elseif ($cur && preg_match('/^([A-D])\s*[).:]\s*(.+)$/u', $line, $m)) {
                $cur['answers'][$letters[strtoupper($m[1])]] = trim($m[2]);
            } elseif (preg_match('/^Regeln\s*:\s*(.+)$/iu', $line, $m)) {
                $rules = trim($m[1]);
            } elseif (!$cur && $title === '') {
                $title = $line;
            }
        }
        if ($cur) {
            $questions[] = $cur;
        }

        if (!$questions) {
            throw new \InvalidArgumentException('Keine Fragen gefunden (Zeilen müssen mit „Frage 1:“ beginnen)');
        }
        foreach ($questions as $i => $q) {
            ksort($q['answers']);
            if (count($q['answers']) !== 4) {
                throw new \InvalidArgumentException(sprintf('Frage %d: braucht genau die Antworten A) bis D)', $i + 1));
            }
            if ($q['correct'] === null) {
                throw new \InvalidArgumentException(sprintf('Frage %d: „Richtige Antwort: X)“ fehlt', $i + 1));
            }
            $q['answers'] = array_values($q['answers']);
            $questions[$i] = $q;
        }

        return ['title' => $title ?: 'Trivia-Quiz', 'rules' => $rules, 'questions' => $questions];
    }

    private static function lock(array $s): array
    {
        $s['quiz']['phase'] = 'locked';
        $s['quiz']['lockedAt'] = microtime(true);

        return $s;
    }

    private static function reveal(array $s): array
    {
        $s['quiz']['phase'] = 'reveal';

        return self::score($s);
    }

    /** Ergebnis der aktuellen Frage festhalten (auch nach einer Hand-Korrektur). */
    private static function score(array $s): array
    {
        $q = $s['quiz'];
        $i = $q['index'];
        $s['quiz']['results'][$i] = ['answers' => $q['answers'], 'correct' => $q['questions'][$i]['correct']];

        return $s;
    }

    /** Quiz-Sieg buchen: mehr richtige Antworten → volle Punkte, Gleichstand (mit Treffern) → beide die Hälfte. */
    private static function bookWin(array $s): array
    {
        $s = self::unbookWin($s);
        [$a, $b] = self::tally($s['quiz']['results']);
        $points = $s['quiz']['points'];
        if ($points < 1 || ($a === 0 && $b === 0)) {
            return $s;
        }
        $book = function (int $team, int $delta, string $reason) use (&$s) {
            $s['log'][] = ['id' => bin2hex(random_bytes(6)), 'at' => microtime(true), 'team' => $team, 'delta' => $delta, 'reason' => $reason, 'game' => self::GAME];
        };
        if ($a === $b) {
            $book(0, intdiv($points + 1, 2), sprintf('Quiz (Unentschieden %d:%d)', $a, $b));
            $book(1, intdiv($points + 1, 2), sprintf('Quiz (Unentschieden %d:%d)', $a, $b));
        } else {
            $book($a > $b ? 0 : 1, $points, sprintf('Quiz-Sieg (%d:%d)', $a, $b));
        }

        return $s;
    }

    private static function unbookWin(array $s): array
    {
        $s['log'] = array_values(array_filter($s['log'], fn ($e) => ($e['game'] ?? null) !== self::GAME));

        return $s;
    }

    private static function bothIn(array $q): bool
    {
        return $q['answers'][0] !== null && $q['answers'][1] !== null;
    }

    /** Richtige Antworten pro Team über alle aufgelösten Fragen. */
    private static function tally(array $results): array
    {
        $n = [0, 0];
        foreach ($results as $r) {
            foreach ([0, 1] as $t) {
                if ($r['answers'][$t] === $r['correct']) {
                    ++$n[$t];
                }
            }
        }

        return $n;
    }

    private const DEFAULT_SOURCE = <<<'TXT'
        TRIVIA-QUIZ
        Regeln: 10 Multiple-Choice-Fragen, je 4 Antwortmöglichkeiten, genau eine ist richtig. Pro Frage 30 Sekunden.

        Frage 1: Welches Spiel wurde NICHT von Valve entwickelt?
        A) Portal
        B) Left 4 Dead
        C) Team Fortress
        D) Garry's Mod
        Richtige Antwort: D) Garry's Mod

        Frage 2: Welche dieser Kreaturen gab es in der ersten Version von Minecraft?
        A) Creeper
        B) Enderman
        C) Villager
        D) Pferd
        Richtige Antwort: A) Creeper

        Frage 3: Welche dieser Konsolen war zuerst auf dem Markt?
        A) Nintendo 64
        B) PlayStation 2
        C) Xbox
        D) GameCube
        Richtige Antwort: A) Nintendo 64

        Frage 4: Welches dieser Dinge kann in Die Sims 4 einen Sim töten?
        A) Scham
        B) Langeweile
        C) Traurigkeit
        D) Schlafmangel
        Richtige Antwort: A) Scham

        Frage 5: Wie viele Adernpaare werden über ein gewöhnliches Cat-5e-LAN-Kabel verwendet?
        A) 1
        B) 2
        C) 3
        D) 4
        Richtige Antwort: D) 4

        Frage 6: Wofür steht „inten“?
        A) Intern Kommunizieren
        B) Intelligent Spielen
        C) Intentioniert Sterben
        D) IntelliJ Starten
        Richtige Antwort: C) Intentioniert Sterben

        Frage 7: Wenn ein Destiny-Spieler 2 Wochen nicht duscht, wie viel Wasser spart er durchschnittlich?
        A) 50 Liter
        B) 150 Liter
        C) 300 Liter
        D) 600 Liter
        Richtige Antwort: C) 300 Liter

        Frage 8: Was macht Kalk im Landwirtschafts-Simulator 22?
        A) Verbessert Nährwerte
        B) Verbessert pH-Werte
        C) Tötet Unkraut
        D) Verbessert Wachstum
        Richtige Antwort: B) Verbessert pH-Werte

        Frage 9: Wie wird Moorhuhn-Kart international vermarktet?
        A) Swamp Chicken Racing
        B) Moorhen-Kart
        C) Crazy Chicken Kart
        D) Wild Hen Turbo
        Richtige Antwort: C) Crazy Chicken Kart

        Frage 10: Welche Einheit ist in Stronghold Crusader besonders effektiv gegen gepanzerte Einheiten?
        A) Bogenschütze
        B) Armbrustschütze
        C) Schleuderer
        D) Speerträger
        Richtige Antwort: B) Armbrustschütze
        TXT;
}
