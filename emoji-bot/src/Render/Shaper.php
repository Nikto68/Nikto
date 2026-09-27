<?php
declare(strict_types=1);

namespace EmojiBot\Render;

/**
 * Persian/Arabic text shaping + bidi reordering for GD.
 *
 * GD/FreeType draws code points one by one, left to right, without OpenType shaping.
 * So we (1) replace every letter with its contextual presentation form and
 * (2) reorder the string into visual (left-to-right) order.
 */
final class Shaper
{
    /** code point => [isolated, final, initial, medial] (null = form does not exist) */
    private const FORMS = [
        0x0621 => [0xFE80, null, null, null],
        0x0622 => [0xFE81, 0xFE82, null, null],
        0x0623 => [0xFE83, 0xFE84, null, null],
        0x0624 => [0xFE85, 0xFE86, null, null],
        0x0625 => [0xFE87, 0xFE88, null, null],
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],
        0x0627 => [0xFE8D, 0xFE8E, null, null],
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0xFE93, 0xFE94, null, null],
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0xFEA9, 0xFEAA, null, null],
        0x0630 => [0xFEAB, 0xFEAC, null, null],
        0x0631 => [0xFEAD, 0xFEAE, null, null],
        0x0632 => [0xFEAF, 0xFEB0, null, null],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0xFEED, 0xFEEE, null, null],
        0x0649 => [0xFEEF, 0xFEF0, 0xFBE8, 0xFBE9],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59],
        0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],
        0x0698 => [0xFB8A, 0xFB8B, null, null],
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91],
        0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95],
        0x06C0 => [0xFBA4, 0xFBA5, null, null],
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],
    ];

    /** lam + alef variant => [isolated ligature, final ligature] */
    private const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6],
        0x0623 => [0xFEF7, 0xFEF8],
        0x0625 => [0xFEF9, 0xFEFA],
        0x0627 => [0xFEFB, 0xFEFC],
    ];

    private const TATWEEL = 0x0640;
    private const ZWNJ = 0x200C;
    private const ZWJ = 0x200D;

    private const MIRROR = [0x28 => 0x29, 0x29 => 0x28, 0x5B => 0x5D, 0x5D => 0x5B, 0x7B => 0x7D, 0x7D => 0x7B, 0x3C => 0x3E, 0x3E => 0x3C, 0xAB => 0xBB, 0xBB => 0xAB];

    /** Full pipeline: logical UTF-8 string => visual-order array of code points ready for GD. */
    public static function visual(string $text): array
    {
        return self::reorder(self::shape(self::codepoints($text)));
    }

    public static function hasRtl(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    /** @return int[] */
    public static function codepoints(string $text): array
    {
        $out = [];
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $out[] = mb_ord($ch, 'UTF-8');
        }
        return $out;
    }

    public static function toUtf8(array $cps): string
    {
        $s = '';
        foreach ($cps as $cp) {
            $s .= mb_chr($cp, 'UTF-8');
        }
        return $s;
    }

    private static function isTransparent(int $cp): bool
    {
        return ($cp >= 0x064B && $cp <= 0x065F) || $cp === 0x0670 || ($cp >= 0x06D6 && $cp <= 0x06ED);
    }

    /** Can this character connect to the character that follows it (to its left)? */
    private static function joinsNext(int $cp): bool
    {
        return $cp === self::TATWEEL || $cp === self::ZWJ || (isset(self::FORMS[$cp]) && self::FORMS[$cp][2] !== null);
    }

    /** Can this character connect to the character that precedes it (to its right)? */
    private static function joinsPrev(int $cp): bool
    {
        return $cp === self::TATWEEL || $cp === self::ZWJ || (isset(self::FORMS[$cp]) && self::FORMS[$cp][1] !== null);
    }

    /**
     * Contextual shaping. Diacritics are dropped (GD cannot position marks); ZWNJ/ZWJ are
     * honoured for joining and then removed. Isolated forms use the base code point, which
     * every Persian font maps to the isolated glyph (some fonts lack the FExx isolated forms).
     * @param int[] $cps
     * @return int[]
     */
    public static function shape(array $cps): array
    {
        $cps = array_values(array_filter($cps, fn ($c) => !self::isTransparent($c)));
        $n = count($cps);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $cp = $cps[$i];
            if (!isset(self::FORMS[$cp])) {
                if ($cp !== self::ZWNJ && $cp !== self::ZWJ) {
                    $out[] = $cp;
                }
                continue;
            }
            $prev = $i > 0 ? $cps[$i - 1] : null;
            $next = $i + 1 < $n ? $cps[$i + 1] : null;
            $joinPrev = $prev !== null && self::joinsNext($prev) && self::FORMS[$cp][1] !== null;

            if ($cp === 0x0644 && $next !== null && isset(self::LAM_ALEF[$next])) {
                $out[] = self::LAM_ALEF[$next][$joinPrev ? 1 : 0];
                $i++;
                continue;
            }

            $joinNext = $next !== null && self::FORMS[$cp][2] !== null && self::joinsPrev($next);
            $form = match (true) {
                $joinPrev && $joinNext => 3,
                $joinPrev => 1,
                $joinNext => 2,
                default => 0,
            };
            $out[] = $form === 0 ? $cp : (self::FORMS[$cp][$form] ?? $cp);
        }
        return $out;
    }

    private static function type(int $cp): string
    {
        if (($cp >= 0x30 && $cp <= 0x39) || ($cp >= 0x06F0 && $cp <= 0x06F9) || ($cp >= 0x0660 && $cp <= 0x0669)) {
            return 'N'; // numbers: laid out left-to-right, but they do not decide paragraph direction
        }
        if (($cp >= 0x0600 && $cp <= 0x06FF) || ($cp >= 0xFB50 && $cp <= 0xFDFF) || ($cp >= 0xFE70 && $cp <= 0xFEFF)) {
            return 'R';
        }
        if (preg_match('/^\p{L}$/u', mb_chr($cp, 'UTF-8'))) {
            return 'L';
        }
        return 'X'; // neutral: spaces, punctuation, symbols
    }

    /**
     * Simplified Unicode bidi for a single line: numbers and Latin keep their order,
     * RTL runs are reversed, neutrals take the direction of their surroundings.
     * @param int[] $cps
     * @return int[]
     */
    public static function reorder(array $cps): array
    {
        $n = count($cps);
        if ($n === 0) {
            return [];
        }
        $types = array_map([self::class, 'type'], $cps);
        $base = 'L';
        foreach ($types as $t) {
            if ($t === 'R' || $t === 'L') {
                $base = $t;
                break;
            }
        }
        if ($base === 'L' && !in_array('R', $types, true)) {
            return $cps;
        }

        // Resolve every character to L or R.
        $dir = [];
        foreach ($types as $i => $t) {
            $dir[$i] = match ($t) {
                'R' => 'R',
                'L', 'N' => 'L',
                default => null,
            };
        }
        for ($i = 0; $i < $n; $i++) {
            if ($dir[$i] !== null) {
                continue;
            }
            $before = null;
            for ($j = $i - 1; $j >= 0; $j--) {
                if ($types[$j] !== 'X') {
                    $before = $dir[$j];
                    break;
                }
            }
            $after = null;
            for ($j = $i + 1; $j < $n; $j++) {
                if ($types[$j] !== 'X') {
                    $after = $dir[$j];
                    break;
                }
            }
            $dir[$i] = ($before !== null && $before === $after) ? $before : $base;
        }

        // Split into runs.
        $runs = [];
        $cur = [$dir[0], [$cps[0]]];
        for ($i = 1; $i < $n; $i++) {
            if ($dir[$i] === $cur[0]) {
                $cur[1][] = $cps[$i];
            } else {
                $runs[] = $cur;
                $cur = [$dir[$i], [$cps[$i]]];
            }
        }
        $runs[] = $cur;

        foreach ($runs as &$run) {
            if ($run[0] === 'R') {
                $run[1] = array_map(fn ($c) => self::MIRROR[$c] ?? $c, array_reverse($run[1]));
            }
        }
        unset($run);
        if ($base === 'R') {
            $runs = array_reverse($runs);
        }
        $out = [];
        foreach ($runs as $run) {
            array_push($out, ...$run[1]);
        }
        return $out;
    }
}
