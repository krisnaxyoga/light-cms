<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class LightCMS extends BaseConfig
{
    /**
     * Hard memory ceiling target for a single request (see PRD §8.1).
     * This is advisory only — enforce via server-level memory_limit.
     */
    public int $memoryLimitMB = 50;

    /**
     * Default pagination cap (PRD §3.6.B).
     */
    public int $maxItemsPerPage = 50;

    /**
     * Path (relative to FCPATH) where themes live.
     */
    public string $themesPath = 'themes/';

    /**
     * Default/fallback theme slug used if none is marked active.
     */
    public string $defaultTheme = 'default';

    /**
     * Page cache TTL, in seconds, for guest requests (PRD §3.6.A).
     */
    public int $pageCacheTTL = 3600;

    /**
     * Query/object cache TTL, in seconds.
     */
    public int $queryCacheTTL = 3600;

    /**
     * SEO defaults used when a post has no seo_meta row yet.
     */
    public array $seoDefaults = [
        'robots_index'  => true,
        'robots_follow' => true,
        'twitter_card'  => 'summary_large_image',
    ];

    /**
     * SEO score thresholds -> traffic light color (PRD §3.1.A.3).
     */
    public array $seoScoreThresholds = [
        'good'    => 80,
        'ok'      => 50,
        // below 'ok' is treated as poor/red
    ];

    /**
     * Roles known to the system, in descending order of privilege
     * (PRD §3.5.A). Kept here so controllers/filters do not hardcode it.
     */
    public array $roles = [
        'super_admin',
        'administrator',
        'editor',
        'author',
        'contributor',
        'subscriber',
    ];

    // -- Automation (PRD ADDENDUM) -------------------------------------

    /**
     * Auto meta-description length target (PRD Addendum §2.2).
     */
    public int $autoMetaDescriptionLength = 155;

    /**
     * A post needs at least this many auto-detected Q/A pairs
     * (heading immediately followed by a paragraph) before the auto
     * schema generator adds FAQPage markup (PRD Addendum §2.3).
     */
    public int $autoFaqMinPairs = 2;

    /**
     * Image pipeline (PRD Addendum §4). Only JPEG/PNG/GIF uploads are
     * processed — everything else (SVG, PDF, audio, video) is stored as-is.
     */
    public int $imageMaxWidth = 1920;
    public int $imageQuality = 85;
    public bool $imageGenerateWebP = true;

    /**
     * Hard cap on the generated WebP variant's file size, in bytes — every
     * upload's 'webp' variant (ImageProcessor::toWebP()) is compressed
     * (and, if that alone isn't enough, downscaled) until it fits under
     * this, however large or high-quality the original was.
     */
    public int $imageWebPMaxBytes = 120 * 1024;

    /** name => [width, height], cropped with 'fit' (center crop). */
    public array $imageThumbnailSizes = [
        'thumbnail' => [150, 150],
        'medium'    => [300, 300],
        'large'     => [1024, 1024],
    ];

    /**
     * Retention windows for `php spark lightcms:cleanup` (PRD Addendum §10).
     */
    public int $cleanupTrashDays = 30;
    public int $cleanupSpamCommentDays = 30;
    public int $cleanupActivityLogDays = 90;
    public int $cleanupNotFoundLogDays = 30;

    /**
     * Dashboard notifications (PRD Addendum §13). A post scoring below
     * this on save gets a one-time "low SEO score" notification.
     */
    public int $lowSeoScoreThreshold = 50;

    /**
     * A 404 notification fires once per this many minutes if hits to
     * *distinct* unknown URLs exceed the threshold within that window.
     */
    public int $notFoundSpikeThreshold = 20;
    public int $notFoundSpikeWindowMinutes = 60;
}
