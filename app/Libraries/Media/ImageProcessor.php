<?php

namespace App\Libraries\Media;

use Config\LightCMS as LightCMSConfig;

/**
 * Upload-time image pipeline (PRD ADDENDUM §4): resize/compress in place,
 * generate a WebP copy and a handful of thumbnail sizes, and suggest alt
 * text from the original filename. Only real raster images (JPEG/PNG/GIF)
 * are touched — SVG, PDF, audio, and video pass through untouched.
 */
class ImageProcessor
{
    protected const PROCESSABLE_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];

    /**
     * @param string $absolutePath full filesystem path to the just-uploaded file
     * @param string $relativePath its path relative to FCPATH (used to derive variant filenames)
     *
     * @return array{width: ?int, height: ?int, variants: array<string, string>}
     */
    public function process(string $absolutePath, string $relativePath): array
    {
        $config    = config(LightCMSConfig::class);
        $imageInfo = @getimagesize($absolutePath);

        if ($imageInfo === false || ! in_array($imageInfo[2], self::PROCESSABLE_TYPES, true)) {
            return ['width' => $imageInfo[0] ?? null, 'height' => $imageInfo[1] ?? null, 'variants' => []];
        }

        // Auto-resize (only shrinks; never upscales) then re-save through
        // the handler so the quality/compression setting is always applied.
        $handler = \Config\Services::image('gd', null, false)->withFile($absolutePath);

        if ($imageInfo[0] > $config->imageMaxWidth) {
            $handler->resize($config->imageMaxWidth, 0, true, 'width');
        }

        $handler->save($absolutePath, $config->imageQuality);

        $finalInfo = @getimagesize($absolutePath) ?: $imageInfo;
        $variants  = [];

        if ($config->imageGenerateWebP) {
            $webpRelative = $this->withNewExtension($relativePath, 'webp');

            if ($this->toWebP($absolutePath, FCPATH . $webpRelative, $config->imageQuality)) {
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

        return ['width' => $finalInfo[0], 'height' => $finalInfo[1], 'variants' => $variants];
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

    protected function toWebP(string $sourcePath, string $targetPath, int $quality): bool
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        $image = match ($extension) {
            'png'          => imagecreatefrompng($sourcePath),
            'gif'          => imagecreatefromgif($sourcePath),
            'jpg', 'jpeg'  => imagecreatefromjpeg($sourcePath),
            default        => false,
        };

        if ($image === false) {
            return false;
        }

        imagepalettetotruecolor($image);
        imagesavealpha($image, true);
        $ok = imagewebp($image, $targetPath, $quality);
        imagedestroy($image);

        return $ok;
    }

    protected function withSuffix(string $relativePath, string $suffix): string
    {
        $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
        $base      = preg_replace('/\.[^.]+$/', '', $relativePath);

        return "{$base}{$suffix}.{$extension}";
    }

    protected function withNewExtension(string $relativePath, string $newExtension): string
    {
        $base = preg_replace('/\.[^.]+$/', '', $relativePath);

        return "{$base}.{$newExtension}";
    }
}
