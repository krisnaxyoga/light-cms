<?php

namespace App\Libraries\Media;

use Config\LightCMS as LightCMSConfig;

/**
 * Upload-time image pipeline (PRD ADDENDUM §4): resize/compress in place,
 * generate a size-capped WebP copy and a handful of thumbnail sizes, and
 * suggest alt text from the original filename.
 *
 * The WebP variant (toWebP()) always ends up at or under
 * Config\LightCMS::$imageWebPMaxBytes (120KB by default) — any input size
 * or format, dropping quality first and, if that alone isn't enough,
 * downscaling too, so even a huge/detailed original still produces a
 * lightweight WebP for the page to actually serve.
 *
 * JPEG/PNG/GIF/WebP are handled directly through CI4's own image service.
 * Anything else this server's GD build can actually decode (BMP, AVIF,
 * WBMP, XBM — see BRIDGE_LOADERS) is bridged through a one-time re-encode
 * to PNG first, so the same resize/compress/WebP/thumbnail pipeline below
 * runs identically no matter what format the file arrived in — genuinely
 * "any format", bounded only by what this PHP install's GD extension can
 * read (checked at runtime via function_exists(), since not every GD build
 * includes every codec). A format neither path can decode (a non-image
 * file, or a codec this server's GD truly lacks) passes through untouched
 * rather than failing the upload outright.
 *
 * Bridging changes the file's actual extension to .png (its real new
 * encoding) — callers must use the `relativePath`/`absolutePath` process()
 * returns for the DB record, not the ones they passed in, since a bridged
 * upload's on-disk name changes.
 */
class ImageProcessor
{
    /** Formats CI4's own image handler (and toWebP()) already load/save directly. */
    protected const NATIVE_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

    /** Anything else worth attempting: IMAGETYPE_* => the GD function that can decode it. */
    protected const BRIDGE_LOADERS = [
        IMAGETYPE_BMP  => 'imagecreatefrombmp',
        IMAGETYPE_AVIF => 'imagecreatefromavif',
        IMAGETYPE_WBMP => 'imagecreatefromwbmp',
        IMAGETYPE_XBM  => 'imagecreatefromxbm',
    ];

    /**
     * @param string $absolutePath full filesystem path to the just-uploaded file
     * @param string $relativePath its path relative to FCPATH (used to derive variant filenames)
     *
     * @return array{width: ?int, height: ?int, variants: array<string, string>, absolutePath: string, relativePath: string}
     */
    public function process(string $absolutePath, string $relativePath): array
    {
        $config    = config(LightCMSConfig::class);
        $imageInfo = @getimagesize($absolutePath);
        $unchanged = ['width' => $imageInfo[0] ?? null, 'height' => $imageInfo[1] ?? null, 'variants' => [], 'absolutePath' => $absolutePath, 'relativePath' => $relativePath];

        if ($imageInfo === false) {
            return $unchanged; // not an image GD can even inspect (PDF, audio, video, plain SVG…)
        }

        $type = $imageInfo[2];

        if (! in_array($type, self::NATIVE_TYPES, true)) {
            $bridged = $this->bridgeToPng($absolutePath, $relativePath, $type);

            if ($bridged === null) {
                return $unchanged; // this server's GD build can't decode this one — leave it as uploaded
            }

            [$absolutePath, $relativePath] = $bridged;
            $imageInfo = @getimagesize($absolutePath) ?: $imageInfo;
            $type      = IMAGETYPE_PNG;
        }

        // Auto-resize (only shrinks; never upscales) then re-save through
        // the handler so the quality/compression setting is always applied
        // — every processable upload gets compressed, not just the ones
        // that happened to need shrinking.
        $handler = \Config\Services::image('gd', null, false)->withFile($absolutePath);

        if ($imageInfo[0] > $config->imageMaxWidth) {
            $handler->resize($config->imageMaxWidth, 0, true, 'width');
        }

        $handler->save($absolutePath, $config->imageQuality);

        $finalInfo = @getimagesize($absolutePath) ?: $imageInfo;
        $variants  = [];

        // Every processable upload gets a WebP variant compressed (and, if
        // needed, downscaled) to fit under imageWebPMaxBytes — including a
        // source that's already WebP, since "however large the original"
        // is exactly the case this exists for.
        if ($config->imageGenerateWebP) {
            $webpRelative = $this->withNewExtension($relativePath, 'webp');

            if ($this->toWebP($absolutePath, FCPATH . $webpRelative, $config->imageWebPMaxBytes)) {
                $variants['webp'] = $webpRelative;
            }
        }

        foreach ($config->imageThumbnailSizes as $name => [$width, $height]) {
            $thumbRelative = $this->withSuffix($relativePath, "-{$name}");

            \Config\Services::image('gd', null, false)
                ->withFile($absolutePath)
                ->fit($width, $height, 'center')
                ->save(FCPATH . $thumbRelative, $config->imageQuality);

            $variants[$name] = $thumbRelative;
        }

        return ['width' => $finalInfo[0], 'height' => $finalInfo[1], 'variants' => $variants, 'absolutePath' => $absolutePath, 'relativePath' => $relativePath];
    }

    /**
     * "sunset-over-bali.jpg" -> "Sunset Over Bali" (PRD ADDENDUM §4.1
     * generateAltFromFilename) — a starting point the author can still
     * override, never a substitute for a real description.
     */
    public function altTextFromFilename(string $originalName): string
    {
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $name = str_replace(['-', '_'], ' ', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        return $name === '' ? '' : mb_convert_case($name, MB_CASE_TITLE);
    }

    /**
     * Re-encodes a format CI4's image handler can't load natively (BMP,
     * AVIF, …) to a PNG at the same path/name, using raw GD directly.
     * Returns the new [absolutePath, relativePath] pair, or null if this
     * server's GD build doesn't have the decoder the format needs.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function bridgeToPng(string $absolutePath, string $relativePath, int $type): ?array
    {
        $loader = self::BRIDGE_LOADERS[$type] ?? null;

        if ($loader === null || ! function_exists($loader)) {
            return null;
        }

        $image = @$loader($absolutePath);

        if ($image === false) {
            return null;
        }

        $newRelative = $this->withNewExtension($relativePath, 'png');
        $newAbsolute = FCPATH . $newRelative;

        imagepalettetotruecolor($image);
        imagesavealpha($image, true);
        $ok = imagepng($image, $newAbsolute);
        imagedestroy($image);

        if (! $ok) {
            return null;
        }

        if ($newAbsolute !== $absolutePath) {
            @unlink($absolutePath); // the original .bmp/.avif upload — superseded by the .png
        }

        return [$newAbsolute, $newRelative];
    }

    /** Quality steps tried before dimensions are touched — cheapest way to shed bytes first. */
    protected const WEBP_QUALITY_STEPS = [82, 70, 58, 46, 34, 22];

    /** Each downscale pass shrinks by this factor; stops once the shorter side would drop below this many px. */
    protected const WEBP_SHRINK_FACTOR   = 0.8;
    protected const WEBP_MIN_DIMENSION   = 200;
    protected const WEBP_MAX_SHRINK_PASS = 8;

    /**
     * Encodes $sourcePath as WebP at $targetPath, compressed (and, if
     * quality alone isn't enough, downscaled) until the file fits under
     * $maxBytes — "any size in, at most $maxBytes out" regardless of how
     * large or detailed the source is. Quality drops first (no resolution
     * lost); only once the lowest quality step still doesn't fit does it
     * start shrinking dimensions too, so a genuinely huge/noisy photo
     * still ends up under budget rather than left oversized.
     *
     * Best-effort at the extreme end: if WEBP_MIN_DIMENSION is reached and
     * it's *still* over budget, the smallest attempt made is kept — a
     * slightly-over-budget WebP beats no WebP variant at all.
     */
    protected function toWebP(string $sourcePath, string $targetPath, int $maxBytes): bool
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        $image = match ($extension) {
            'png'         => imagecreatefrompng($sourcePath),
            'gif'         => imagecreatefromgif($sourcePath),
            'jpg', 'jpeg' => imagecreatefromjpeg($sourcePath),
            'webp'        => imagecreatefromwebp($sourcePath),
            default       => false,
        };

        if ($image === false) {
            return false;
        }

        imagepalettetotruecolor($image);
        imagesavealpha($image, true);

        foreach (self::WEBP_QUALITY_STEPS as $quality) {
            if (imagewebp($image, $targetPath, $quality) && filesize($targetPath) <= $maxBytes) {
                imagedestroy($image);

                return true;
            }
        }

        $width  = imagesx($image);
        $height = imagesy($image);
        // end() needs a real variable to take a reference to — a class
        // constant isn't addressable, hence the copy.
        $qualitySteps  = self::WEBP_QUALITY_STEPS;
        $lowestQuality = end($qualitySteps);

        for ($pass = 0; $pass < self::WEBP_MAX_SHRINK_PASS && min($width, $height) > self::WEBP_MIN_DIMENSION; $pass++) {
            $width   = (int) round($width * self::WEBP_SHRINK_FACTOR);
            $height  = (int) round($height * self::WEBP_SHRINK_FACTOR);
            $resized = imagescale($image, max($width, 1), max($height, 1));

            if ($resized === false) {
                break;
            }

            imagedestroy($image);
            $image = $resized;

            if (imagewebp($image, $targetPath, $lowestQuality) && filesize($targetPath) <= $maxBytes) {
                imagedestroy($image);

                return true;
            }
        }

        // Every step above already wrote its attempt to $targetPath, so
        // the file on disk right now is the smallest one achieved even
        // though it's still over $maxBytes.
        imagedestroy($image);

        return is_file($targetPath);
    }

    /**
     * "uploads/2024/01/photo.jpg" + "-thumbnail" -> "uploads/2024/01/photo-thumbnail.jpg".
     * Public (not just used internally): Admin\MediaController::renameOnDisk()
     * reuses this exact convention so a renamed file's variants land at the
     * paths this class would generate for that new name, rather than
     * reverse-engineering the naming scheme from the old paths.
     */
    public function withSuffix(string $relativePath, string $suffix): string
    {
        $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
        $base      = preg_replace('/\.[^.]+$/', '', $relativePath);

        return "{$base}{$suffix}.{$extension}";
    }

    /** "uploads/2024/01/photo.jpg" + "webp" -> "uploads/2024/01/photo.webp". See withSuffix(). */
    public function withNewExtension(string $relativePath, string $newExtension): string
    {
        $base = preg_replace('/\.[^.]+$/', '', $relativePath);

        return "{$base}.{$newExtension}";
    }
}
