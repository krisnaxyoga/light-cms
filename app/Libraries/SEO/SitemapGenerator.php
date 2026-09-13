<?php

namespace App\Libraries\SEO;

use App\Models\CategoryModel;
use App\Models\PostModel;
use Config\Services;

/**
 * XML sitemap generator (PRD §3.1.B.1). Splits output into <= 50,000 URLs
 * per file and writes a sitemap index when more than one chunk is needed.
 *
 * Multi-language: one sitemap lists every language's URLs, and each entry
 * that has translations carries <xhtml:link rel="alternate" hreflang>
 * pointers to them (Google's recommended single-sitemap form). With the
 * feature off the output is the same single-language sitemap as before.
 */
class SitemapGenerator
{
    protected const MAX_URLS_PER_FILE = 50000;
    protected const XHTML_NS          = 'http://www.w3.org/1999/xhtml';

    protected PostModel $posts;
    protected CategoryModel $categories;

    public function __construct(?PostModel $posts = null, ?CategoryModel $categories = null)
    {
        $this->posts      = $posts ?? new PostModel();
        $this->categories = $categories ?? new CategoryModel();
    }

    /**
     * Collect every indexable URL as
     * ['loc', 'lastmod', 'changefreq', 'priority', 'alternates' => [code => url]].
     */
    public function collectUrls(): array
    {
        $locale    = Services::locale();
        $multilang = $locale->enabled();
        $languages = $locale->active();
        $urls      = [];

        // Home page(s) — one per active language, cross-linked.
        $homes = [];

        foreach ($languages as $lang) {
            $homes[$lang['code']] = $locale->homeUrl($lang['code']);
        }

        foreach ($homes as $code => $loc) {
            $urls[] = [
                'loc'        => $loc,
                'lastmod'    => $this->toDate(null),
                'changefreq' => 'daily',
                'priority'   => '1.0',
                'alternates' => $multilang && count($homes) > 1 ? $homes : [],
            ];
        }

        $rows = $this->posts
            ->select('id, slug, locale, translation_group_id, post_type, updated_at, published_at')
            ->where('status', 'published')
            ->whereIn('post_type', ['post', 'page'])
            ->orderBy('post_type', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        // Group siblings once so each entry can list its translations
        // without a query per post.
        $groups = [];

        foreach ($rows as $row) {
            $groups[$row['translation_group_id'] ?? 'p' . $row['id']][$row['locale']] = post_url($row, $row['locale']);
        }

        foreach ($rows as $row) {
            $siblings = $groups[$row['translation_group_id'] ?? 'p' . $row['id']] ?? [];

            $urls[] = [
                'loc'        => post_url($row, $row['locale']),
                'lastmod'    => $this->toDate($row['updated_at'] ?? $row['published_at']),
                'changefreq' => $row['post_type'] === 'post' ? 'weekly' : 'monthly',
                'priority'   => $row['post_type'] === 'post' ? '0.7' : '0.5',
                'alternates' => $multilang && count($siblings) > 1 ? $siblings : [],
            ];
        }

        // Categories are shared across languages: the same slug exists in
        // every language and lists that language's posts.
        foreach ($this->categories->findAll() as $category) {
            $variants = [];

            foreach ($languages as $lang) {
                $variants[$lang['code']] = $locale->url('category/' . $category['slug'], $lang['code']);
            }

            foreach ($variants as $loc) {
                $urls[] = [
                    'loc'        => $loc,
                    'lastmod'    => $this->toDate($category['created_at']),
                    'changefreq' => 'weekly',
                    'priority'   => '0.4',
                    'alternates' => $multilang && count($variants) > 1 ? $variants : [],
                ];
            }
        }

        return $urls;
    }

    public function urlsetXml(array $urls): string
    {
        // Only declare the xhtml namespace when something will actually use
        // it, so a single-language sitemap stays byte-for-byte what it was
        // before multi-language existed. (composer.json targets PHP ^8.2,
        // so array_any() — PHP 8.4+ — isn't available here.)
        $needsXhtml = false;

        foreach ($urls as $url) {
            if (! empty($url['alternates'])) {
                $needsXhtml = true;
                break;
            }
        }

        $xml = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            . ($needsXhtml ? ' xmlns:xhtml="' . self::XHTML_NS . '"' : '')
            . '/>'
        );

        foreach ($urls as $url) {
            $node = $xml->addChild('url');
            $node->addChild('loc', htmlspecialchars($url['loc'], ENT_XML1));
            $node->addChild('lastmod', $url['lastmod']);
            $node->addChild('changefreq', $url['changefreq']);
            $node->addChild('priority', $url['priority']);

            $alternates = $url['alternates'] ?? [];

            if ($alternates === []) {
                continue;
            }

            $xDefault = $alternates[Services::locale()->defaultCode()] ?? reset($alternates);
            $alternates['x-default'] = $xDefault;

            foreach ($alternates as $code => $href) {
                $link = $node->addChild('xhtml:link', null, self::XHTML_NS);
                $link->addAttribute('rel', 'alternate');
                $link->addAttribute('hreflang', $code);
                $link->addAttribute('href', $href);
            }
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
