<?php
declare(strict_types=1);

namespace EmojiBot\Render;

use GdImage;
use RuntimeException;

/**
 * Encodes frames into a Telegram-compatible custom emoji video:
 * WEBM container, VP9 with alpha (yuva420p), 100x100, <= 3 s, <= 30 fps, no audio, <= 64 KB.
 */
final class VideoEncoder
{
    public const MAX_BYTES = 64 * 1024;

    private ?bool $available = null;

    public function __construct(private readonly string $ffmpeg, private readonly string $tmpDir)
    {
    }

    public function available(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }
        if ($this->ffmpeg === '' || !function_exists('proc_open')) {
            return $this->available = false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('proc_open', $disabled, true)) {
            return $this->available = false;
        }
        $marker = $this->tmpDir . '/ffmpeg-check-' . md5($this->ffmpeg) . '.txt';
        if (is_file($marker) && filemtime($marker) > time() - 3600) {
            return $this->available = trim((string) file_get_contents($marker)) === '1';
        }
        [$code, $out] = $this->run([$this->ffmpeg, '-hide_banner', '-encoders']);
        $ok = $code === 0 && str_contains($out, 'libvpx-vp9');
        @file_put_contents($marker, $ok ? '1' : '0');
        return $this->available = $ok;
    }

    /**
     * @param GdImage[] $frames all the same size
     */
    public function encode(array $frames, string $outPath, int $fps = Renderer::FPS): void
    {
        if (!$this->available()) {
            throw new RuntimeException('ffmpeg with libvpx-vp9 is not available');
        }
        $dir = $this->tmpDir . '/enc-' . bin2hex(random_bytes(6));
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('cannot create temp dir');
        }
        try {
            foreach ($frames as $i => $f) {
                imagepng($f, sprintf('%s/f%03d.png', $dir, $i), 1);
            }
            $attempts = [['-crf', '30'], ['-crf', '40'], ['-crf', '50'], ['-crf', '63', '-b:v', '120k']];
            foreach ($attempts as $quality) {
                @unlink($outPath);
                $cmd = [
                    $this->ffmpeg, '-hide_banner', '-loglevel', 'error', '-y',
                    '-framerate', (string) $fps, '-i', $dir . '/f%03d.png',
                    '-c:v', 'libvpx-vp9', '-pix_fmt', 'yuva420p', '-auto-alt-ref', '0',
                    ...(in_array('-b:v', $quality, true) ? [] : ['-b:v', '0']),
                    ...$quality,
                    '-deadline', 'good', '-cpu-used', '4', '-row-mt', '1',
                    '-an', '-f', 'webm', $outPath,
                ];
                [$code, $out] = $this->run($cmd, 120);
                if ($code !== 0 || !is_file($outPath)) {
                    throw new RuntimeException('ffmpeg failed: ' . mb_substr(trim($out), 0, 300));
                }
                clearstatcache(true, $outPath);
                if (filesize($outPath) <= self::MAX_BYTES) {
                    return;
                }
            }
            throw new RuntimeException('encoded video is larger than 64 KB');
        } finally {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    /** @return array{0: int, 1: string} exit code, combined output */
    private function run(array $cmd, int $timeout = 20): array
    {
        $proc = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            return [-1, 'proc_open failed'];
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = '';
        $start = time();
        while (true) {
            $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            $status = proc_get_status($proc);
            if (!$status['running']) {
                $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
                $code = $status['exitcode'];
                break;
            }
            if (time() - $start > $timeout) {
                proc_terminate($proc, 9);
                $code = -2;
                $output .= ' [timeout]';
                break;
            }
            usleep(20_000);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        return [(int) $code, $output];
    }
}
