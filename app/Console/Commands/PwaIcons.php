<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * The app icons, from whatever logo the school actually has.
 *
 * A home-screen icon needs 192 and 512 pixel squares, plus a maskable one with
 * padding so Android's circular mask does not crop the logo's edges off. None of
 * those exist in this project.
 *
 * The source is whatever is really on disk, which is not necessarily what the
 * settings row claims: on the testbed `logo_path` names a file that was deleted.
 * So this looks for the configured logo, then the favicon, then the largest
 * square image in the uploads folder, and says which one it used.
 */
class PwaIcons extends Command
{
    protected $signature = 'pwa:icons {--source= : Use this image instead of the school logo}';

    protected $description = 'Generate the student portal app icons from the school logo.';

    public function handle(): int
    {
        if (!extension_loaded('gd')) {
            $this->error('GD is not available, so images cannot be resized here.');

            return self::FAILURE;
        }

        $source = $this->option('source') ?: $this->findSource();

        if (!$source) {
            $this->error('No image to work from. Put a square PNG in public/uploads/setting, '
                . 'or pass one with --source=path/to/logo.png');

            return self::FAILURE;
        }

        $size = @getimagesize($source);

        if (!$size) {
            $this->error('That file is not an image: ' . $source);

            return self::FAILURE;
        }

        $this->line('  Source: ' . $source . ' (' . $size[0] . 'x' . $size[1] . ')');

        // Said plainly rather than discovered later on a phone.
        if (min($size[0], $size[1]) < 512) {
            $this->warn('  That is smaller than 512px, so the large icon will be soft.');
            $this->line('  It will work. Replacing public/pwa/icon-512.png later needs no code change.');
        }

        $directory = public_path('pwa');

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $this->error('Could not create ' . $directory);

            return self::FAILURE;
        }

        $written = [];

        foreach ([192, 512] as $target) {
            $path = $directory . DIRECTORY_SEPARATOR . 'icon-' . $target . '.png';

            if ($this->render($source, $path, $target, 0)) {
                $written[] = basename($path);
            }
        }

        // Android masks the icon into a circle and eats roughly the outer fifth,
        // so the maskable one is the logo shrunk into the middle.
        $maskable = $directory . DIRECTORY_SEPARATOR . 'icon-maskable-512.png';

        if ($this->render($source, $maskable, 512, 0.2)) {
            $written[] = basename($maskable);
        }

        if ($written === []) {
            $this->error('Nothing was written.');

            return self::FAILURE;
        }

        $this->info('  Wrote ' . implode(', ', $written) . ' into public/pwa');

        return self::SUCCESS;
    }

    /**
     * Whatever logo is really there, in order of preference.
     */
    protected function findSource(): ?string
    {
        $setting = Setting::where('status', '1')->first();

        foreach ([$setting->logo_path ?? null, $setting->favicon_path ?? null] as $candidate) {
            if ($candidate && file_exists(public_path('uploads/setting/' . $candidate))) {
                return public_path('uploads/setting/' . $candidate);
            }
        }

        $this->warn('  The school logo in settings is not on disk; looking for the best square image instead.');

        $best = null;
        $bestSide = 0;

        foreach (glob(public_path('uploads/setting/*.{png,jpg,jpeg}'), GLOB_BRACE) ?: [] as $file) {
            $size = @getimagesize($file);

            if (!$size || $size[0] !== $size[1]) {
                continue;
            }

            if ($size[0] > $bestSide) {
                $best = $file;
                $bestSide = $size[0];
            }
        }

        return $best;
    }

    /**
     * One icon: the source fitted onto a square canvas, centred, with the
     * background colour behind any transparency.
     */
    protected function render(string $source, string $destination, int $side, float $padding): bool
    {
        $image = $this->open($source);

        if (!$image) {
            return false;
        }

        $canvas = imagecreatetruecolor($side, $side);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        // A flat background rather than transparency: a transparent icon shows
        // as a black square on some Android launchers.
        [$r, $g, $b] = $this->background();
        imagefilledrectangle($canvas, 0, 0, $side, $side, imagecolorallocate($canvas, $r, $g, $b));
        imagealphablending($canvas, true);

        $inner = (int) round($side * (1 - 2 * $padding));
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min($inner / $width, $inner / $height);

        $drawWidth = (int) round($width * $scale);
        $drawHeight = (int) round($height * $scale);

        imagecopyresampled(
            $canvas, $image,
            (int) round(($side - $drawWidth) / 2),
            (int) round(($side - $drawHeight) / 2),
            0, 0,
            $drawWidth, $drawHeight,
            $width, $height
        );

        $ok = imagepng($canvas, $destination, 9);

        imagedestroy($canvas);
        imagedestroy($image);

        return (bool) $ok;
    }

    protected function open(string $path)
    {
        $size = @getimagesize($path);

        return match ($size['mime'] ?? null) {
            'image/png' => @imagecreatefrompng($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/gif' => @imagecreatefromgif($path),
            default => null,
        };
    }

    /** @return array{0:int,1:int,2:int} */
    protected function background(): array
    {
        $hex = ltrim((string) config('pwa.background_color', '#ffffff'), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return [255, 255, 255];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
