<?php

namespace App\Domain\LanNight;

/**
 * Gastauftritte: eine Figur lugt unten aus dem Bildschirmrand, spricht einen Satz (MP3) und hat eine Sprechblase.
 *
 * Bild und Sätze liegen in lan-night-media/cameos/<figur>/ (bild.* + 1.mp3, 2.mp3, …).
 * Die Spielleitung löst jeden Auftritt per Knopf aus; der Sprechblasen-Text wird pro Satz gemerkt.
 */
final class LanNightCameo
{
    /** Figur (= Ordnername) → Anzeigename und Platz am unteren Rand (left / center / right) */
    public const CAST = [
        'uffi' => ['name' => 'Uffi', 'spot' => 'left'],
        'bohlen' => ['name' => 'Bohlen', 'spot' => 'center'],
        'advisor' => ['name' => 'Berater', 'spot' => 'right'],
    ];
    public const MAX_LINES = 20;
    private const MAX_TEXT = 200;

    public static function normalize(array $s): array
    {
        $s['cameo'] ??= null;
        $s['cameoTexts'] = is_array($s['cameoTexts'] ?? null) ? $s['cameoTexts'] : [];

        return $s;
    }

    /** @param array<string,int> $lines Anzahl Sätze pro Figur */
    public static function apply(array $s, string $action, array $in, array $lines): array
    {
        switch ($action) {
            case 'cameo.play':
                [$who, $line] = self::line($in, $lines);
                $text = self::text($in['text'] ?? '');
                $s['cameoTexts'][$who][$line] = $text;
                $s['cameo'] = ['id' => bin2hex(random_bytes(6)), 'at' => microtime(true), 'who' => $who, 'line' => $line, 'text' => $text];
                break;

            case 'cameo.text':
                // Nur merken (Feld verlassen), ohne aufzutreten
                [$who, $line] = self::line($in, $lines);
                $s['cameoTexts'][$who][$line] = self::text($in['text'] ?? '');
                break;

            case 'cameo.hide':
                $s['cameo'] = null;
                break;

            default:
                throw new \InvalidArgumentException('Unbekannte Aktion');
        }

        return $s;
    }

    /** @return array{string,int} */
    private static function line(array $in, array $lines): array
    {
        $who = (string) ($in['who'] ?? '');
        if (!isset(self::CAST[$who])) {
            throw new \InvalidArgumentException('Unbekannte Figur');
        }
        $line = $in['line'] ?? null;
        if (!is_int($line) || $line < 0 || $line >= ($lines[$who] ?? 0)) {
            throw new \InvalidArgumentException('Satz nicht gefunden – liegt die MP3 im Ordner?');
        }

        return [$who, $line];
    }

    private static function text(mixed $v): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $v)), 0, self::MAX_TEXT);
    }
}
