<?php

namespace Config;

use App\Libraries\Cache\CacheManager;
use App\Libraries\Cache\QueryCache;
use App\Libraries\Editor\BlockRenderer;
use App\Libraries\Media\ImageProcessor;
use App\Libraries\SEO\Analyzer;
use App\Libraries\SEO\AutoSeoGenerator;
use App\Libraries\SEO\MetaBuilder;
use App\Libraries\SEO\RobotsTxtGenerator;
use App\Libraries\SEO\SchemaGenerator;
use App\Libraries\SEO\SitemapGenerator;
use App\Libraries\Theme\TemplateLoader;
use App\Libraries\Theme\ThemeEngine;
use App\Libraries\WordPress\Renderer as WordPressRenderer;
use App\Libraries\WordPress\Runtime as WordPressRuntime;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /**
     * The active theme's engine, shared per-request — it reads the DB
     * once and loads functions.php once, then every controller/view that
     * needs it reuses the same instance.
     */
    public static function themeEngine($getShared = true): ThemeEngine
    {
        if ($getShared) {
            return static::getSharedInstance('themeEngine');
        }

        return new ThemeEngine();
    }

    public static function templateLoader($getShared = true): TemplateLoader
    {
        if ($getShared) {
            return static::getSharedInstance('templateLoader');
        }

        return new TemplateLoader(static::themeEngine());
    }

    public static function seoAnalyzer($getShared = true): Analyzer
    {
        if ($getShared) {
            return static::getSharedInstance('seoAnalyzer');
        }

        return new Analyzer();
    }

    public static function seoMetaBuilder($getShared = true): MetaBuilder
    {
        if ($getShared) {
            return static::getSharedInstance('seoMetaBuilder');
        }

        return new MetaBuilder();
    }

    public static function seoSchemaGenerator($getShared = true): SchemaGenerator
    {
        if ($getShared) {
            return static::getSharedInstance('seoSchemaGenerator');
        }

        return new SchemaGenerator();
    }

    public static function sitemapGenerator($getShared = true): SitemapGenerator
    {
        if ($getShared) {
            return static::getSharedInstance('sitemapGenerator');
        }

        return new SitemapGenerator();
    }

    public static function blockRenderer($getShared = true): BlockRenderer
    {
        if ($getShared) {
            return static::getSharedInstance('blockRenderer');
        }

        return new BlockRenderer();
    }

    public static function cacheManager($getShared = true): CacheManager
    {
        if ($getShared) {
            return static::getSharedInstance('cacheManager');
        }

        return new CacheManager();
    }

    public static function queryCache($getShared = true): QueryCache
    {
        if ($getShared) {
            return static::getSharedInstance('queryCache');
        }

        return new QueryCache();
    }

    public static function autoSeoGenerator($getShared = true): AutoSeoGenerator
    {
        if ($getShared) {
            return static::getSharedInstance('autoSeoGenerator');
        }

        return new AutoSeoGenerator();
    }

    public static function robotsTxtGenerator($getShared = true): RobotsTxtGenerator
    {
        if ($getShared) {
            return static::getSharedInstance('robotsTxtGenerator');
        }

        return new RobotsTxtGenerator();
    }

    public static function imageProcessor($getShared = true): ImageProcessor
    {
        if ($getShared) {
            return static::getSharedInstance('imageProcessor');
        }

        return new ImageProcessor();
    }

    /**
     * The WordPress compatibility runtime. Booting is idempotent, so
     * asking for this service never costs more than the level already
     * reached this request (see App\Libraries\WordPress\Runtime).
     */
    public static function wordpress($getShared = true): WordPressRuntime
    {
        return WordPressRuntime::instance();
    }

    public static function wordpressRenderer($getShared = true): WordPressRenderer
    {
        if ($getShared) {
            return static::getSharedInstance('wordpressRenderer');
        }

        return new WordPressRenderer(WordPressRuntime::instance());
    }
}
