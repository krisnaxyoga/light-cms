<?php

namespace App\Libraries\SEO;

use App\Models\CategoryModel;
use App\Models\PostModel;

/**
 * XML sitemap generator (PRD §3.1.B.1). Splits output into <= 50,000 URLs
 * per file and writes a sitemap index when more than one chunk is needed.
 */
class SitemapGenerator
{
    protected const MAX_URLS_PER_FILE = 50000;

    protected PostModel $posts;
    protected CategoryModel $categories;

    public function __construct(?PostModel $posts = null, ?CategoryModel $categories = null)
    {
        $this->posts      = $posts ?? new PostModel();
        $this->categories = $categories ?? new CategoryModel();
    }

    /**
     * Collect every indexable URL as ['loc', 'lastmod', 'changefreq', 'priority'].
     */
    public function collectUrls(): array
    {
        $urls = [];

        foreach (['post', 'page'] as $type) {
            $rows = $this->posts
                ->select('slug, post_type, updated_at, published_at')
                ->where('status', 'published')
                ->where('post_type', $type)
                ->findAll();

            foreach ($rows as $row) {
                $urls[] = [
                    'loc'        => site_url($row['slug']),
                    'lastmod'    => $this->toDate($row['updated_at'] ?? $row['published_at']),
                    'changefreq' => $type === 'post' ? 'weekly' : 'monthly',
                    'priority'   => $type === 'post' ? '0.7' : '0.5',
                ];
            }
        }

        foreach ($this->categories->findAll() as $category) {
            $urls[] = [
                'loc'        => site_url('category/' . $category['slug']),
                'lastmod'    => $this->toDate($category['created_at']),
                'changefreq' => 'weekly',
                'priority'   => '0.4',
            ];
        }

        return $urls;
    }

    public function urlsetXml(array $urls): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');

        foreach ($urls as $url) {
            $node = $xml->addChild('url');
            $node->addChild('loc', htmlspecialchars($url['loc'], ENT_XML1));
            $node->addChild('lastmod', $url['lastmod']);
            $node->addChild('changefreq', $url['changefreq']);
            $node->addChild('priority', $url['priority']);
        }

        return $xml->asXML();
    }

    public function sitemapIndexXml(array $sitemapFiles): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');

        foreach ($sitemapFiles as $file) {
            $node = $xml->addChild('sitemap');
            $node->addChild('loc', htmlspecialchars(base_url($file), ENT_XML1));
            $node->addChild('lastmod', date(DATE_ATOM));
        }

        return $xml->asXML();
    }

    /**
     * Write sitemap.xml (and chunk files + index if needed) to public/.
     * Returns the list of files written, relative to public/.
     */
    public function writeFiles(): array
    {
        $urls   = $this->collectUrls();
        $chunks = array_chunk($urls, self::MAX_URLS_PER_FILE);
        $written = [];

        if (count($chunks) <= 1) {
            $path = FCPATH . 'sitemap.xml';
            file_put_contents($path, $this->urlsetXml($chunks[0] ?? []));

            return ['sitemap.xml'];
        }

        foreach ($chunks as $index => $chunk) {
            $filename = "sitemap-{$index}.xml";
            file_put_contents(FCPATH . $filename, $this->urlsetXml($chunk));
            $written[] = $filename;
        }

        file_put_contents(FCPATH . 'sitemap.xml', $this->sitemapIndexXml($written));
        $written[] = 'sitemap.xml';

        return $written;
    }

    protected function toDate(?string $datetime): string
    {
        $timestamp = $datetime ? strtotime($datetime) : false;

        return date('Y-m-d', $timestamp ?: time());
    }
}
