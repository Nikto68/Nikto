<?php
declare(strict_types=1);

namespace EmojiBot\Render;

use RuntimeException;

/**
 * Font styles with per-character fallback. Coverage is read from each TTF's cmap table.
 */
final class Fonts
{
    /** style id => [title shown in the mini app, css family, font files in fallback order] */
    public const STYLES = [
        // English display fonts first (emoji text is English by default)
        'montserrat' => ['Montserrat',  'Montserrat',      ['Montserrat-Black.ttf', 'Vazirmatn-Black.ttf']],
        'unbounded'  => ['Unbounded',   'Unbounded',       ['Unbounded-Black.ttf', 'Vazirmatn-Black.ttf']],
        'lilita'     => ['Lilita',      'LilitaOne',       ['LilitaOne-Regular.ttf', 'Vazirmatn-Black.ttf']],
        'luckiest'   => ['Luckiest',    'LuckiestGuy',     ['LuckiestGuy-Regular.ttf', 'Vazirmatn-Black.ttf']],
        'titan'      => ['Titan',       'TitanOne',        ['TitanOne-Regular.ttf', 'Vazirmatn-Black.ttf']],
        'bangers'    => ['Bangers',     'Bangers',         ['Bangers-Regular.ttf', 'Vazirmatn-Black.ttf']],
        'russo'      => ['Russo',       'RussoOne',        ['RussoOne-Regular.ttf', 'Vazirmatn-Black.ttf']],
        'rubikmono'  => ['Rubik Mono',  'RubikMonoOne',    ['RubikMonoOne-Regular.ttf', 'Vazirmatn-Black.ttf']],
        'blackops'   => ['Black Ops',   'BlackOpsOne',     ['BlackOpsOne-Regular.ttf', 'Vazirmatn-Black.ttf']],
        'bungee'     => ['Bungee',      'Bungee',          ['Bungee-Regular.ttf', 'Lalezar-Regular.ttf', 'Vazirmatn-Black.ttf']],
        'marker'     => ['Marker',      'PermanentMarker', ['PermanentMarker-Regular.ttf', 'Vazirmatn-Black.ttf']],
        'pixel'      => ['Pixel',       'PressStart2P',    ['PressStart2P-Regular.ttf', 'Vazirmatn-Black.ttf']],
        'pacifico'   => ['Pacifico',    'Pacifico',        ['Pacifico-Regular.ttf', 'Sahel-Black.ttf', 'Vazirmatn-Black.ttf']],
        // Also draw Persian (used when the admin allows Persian text)
        'vazir'      => ['وزیر',        'Vazirmatn',       ['Vazirmatn-Black.ttf']],
        'lalezar'    => ['لاله‌زار',    'Lalezar',         ['Lalezar-Regular.ttf', 'Vazirmatn-Black.ttf']],
        'sahel'      => ['ساحل',        'Sahel',           ['Sahel-Black.ttf', 'Vazirmatn-Black.ttf']],
    ];

    public const DEFAULT = 'montserrat';

    /** @var array<string, array<int, true>> */
    private array $coverage = [];

    public function __construct(private readonly string $dir, private readonly string $cacheDir)
    {
    }

    public function exists(string $style): bool
    {
        return isset(self::STYLES[$style]);
    }

    /** @return string[] absolute paths in fallback order */
    public function files(string $style): array
    {
        $files = self::STYLES[$style][2] ?? self::STYLES[self::DEFAULT][2];
        return array_map(fn ($f) => $this->dir . '/' . $f, $files);
    }

    public function covers(string $file, int $cp): bool
    {
        if (!isset($this->coverage[$file])) {
            $this->coverage[$file] = $this->loadCoverage($file);
        }
        return isset($this->coverage[$file][$cp]);
    }

    /** First font of the style that has a glyph for $cp (last font as the final fallback). */
    public function fontFor(string $style, int $cp): string
    {
        $files = $this->files($style);
        foreach ($files as $f) {
            if ($this->covers($f, $cp)) {
                return $f;
            }
        }
        return $files[count($files) - 1];
    }

    /** True if some font of the style can draw $cp. */
    public function canRender(string $style, int $cp): bool
    {
        foreach ($this->files($style) as $f) {
            if ($this->covers($f, $cp)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int, true> */
    private function loadCoverage(string $file): array
    {
        $stat = @stat($file);
        if (!$stat) {
            throw new RuntimeException("font not found: $file");
        }
        $cacheFile = $this->cacheDir . '/font-' . md5($file . $stat['size'] . $stat['mtime']) . '.json';
        if (is_file($cacheFile)) {
            $list = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($list)) {
                return array_fill_keys($list, true);
            }
        }
        $list = self::parseCmap((string) file_get_contents($file));
        @file_put_contents($cacheFile, json_encode($list), LOCK_EX);
        return array_fill_keys($list, true);
    }

    /**
     * Minimal TrueType/OpenType cmap reader (formats 4 and 12).
     * @return int[] code points that map to a real glyph
     */
    public static function parseCmap(string $data): array
    {
        $u16 = fn (int $o) => unpack('n', $data, $o)[1];
        $u32 = fn (int $o) => unpack('N', $data, $o)[1];
        $numTables = $u16(4);
        $cmap = null;
        for ($i = 0; $i < $numTables; $i++) {
            $rec = 12 + 16 * $i;
            if (substr($data, $rec, 4) === 'cmap') {
                $cmap = $u32($rec + 8);
                break;
            }
        }
        if ($cmap === null) {
            throw new RuntimeException('font has no cmap');
        }
        $best = null;
        $bestScore = -1;
        $n = $u16($cmap + 2);
        for ($i = 0; $i < $n; $i++) {
            $e = $cmap + 4 + 8 * $i;
            $platform = $u16($e);
            $encoding = $u16($e + 2);
            $offset = $cmap + $u32($e + 4);
            $format = $u16($offset);
            $score = match (true) {
                $platform === 3 && $encoding === 10 && $format === 12 => 4,
                $platform === 0 && $format === 12 => 3,
                $platform === 3 && $encoding === 1 && $format === 4 => 2,
                $platform === 0 && $format === 4 => 1,
                default => -1,
            };
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $offset;
            }
        }
        if ($best === null || $bestScore < 0) {
            throw new RuntimeException('no unicode cmap subtable');
        }

        $cps = [];
        $format = $u16($best);
        if ($format === 4) {
            $segX2 = $u16($best + 6);
            $seg = $segX2 >> 1;
            $endBase = $best + 14;
            $startBase = $endBase + $segX2 + 2;
            $deltaBase = $startBase + $segX2;
            $rangeBase = $deltaBase + $segX2;
            for ($s = 0; $s < $seg; $s++) {
                $end = $u16($endBase + 2 * $s);
                $start = $u16($startBase + 2 * $s);
                $delta = $u16($deltaBase + 2 * $s);
                $rangeOffsetPos = $rangeBase + 2 * $s;
                $rangeOffset = $u16($rangeOffsetPos);
                if ($start === 0xFFFF) {
                    continue;
                }
                for ($c = $start; $c <= $end; $c++) {
                    if ($rangeOffset === 0) {
                        $glyph = ($c + $delta) & 0xFFFF;
                    } else {
                        $pos = $rangeOffsetPos + $rangeOffset + 2 * ($c - $start);
                        $glyph = $u16($pos);
                        if ($glyph !== 0) {
                            $glyph = ($glyph + $delta) & 0xFFFF;
                        }
                    }
                    if ($glyph !== 0) {
                        $cps[] = $c;
                    }
                }
            }
        } elseif ($format === 12) {
            $groups = $u32($best + 12);
            for ($g = 0; $g < $groups; $g++) {
                $o = $best + 16 + 12 * $g;
                $start = $u32($o);
                $end = min($u32($o + 4), $start + 0xFFFF);
                if ($start > 0x10FFFF) {
                    continue;
                }
                for ($c = $start; $c <= $end; $c++) {
                    $cps[] = $c;
                }
            }
        }
        return $cps;
    }
}
