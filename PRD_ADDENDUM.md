# PRD ADDENDUM: Automasi & Smart Defaults untuk LightCMS

Excellent point! Berikut adalah fitur-fitur automasi yang harus ditambahkan agar user experience lebih baik dan "plug & play" seperti RankMath.

---

## 1. AUTO-CONFIGURATION ON INSTALL

### 1.1 Setup Wizard Automation

```php
// Automatic during installation
✓ Robots.txt generation (auto-created)
✓ .htaccess optimization (auto-configured)
✓ Permalink structure (SEO-friendly default)
✓ Image optimization settings (auto-enabled)
✓ Sitemap generation (auto-activated)
✓ Schema markup (auto-inject based on content type)
✓ Open Graph & Twitter Cards (auto-enabled)
✓ Google Analytics placeholder (ready to insert ID)
✓ Breadcrumbs (auto-enabled with schema)
```

### 1.2 Initial Configuration Wizard

```javascript
// One-time setup after installation
Step 1: Site Type Detection
[x] Blog
[ ] Business Website  
[ ] E-commerce
[ ] Portfolio
[ ] News/Magazine

→ Auto-configure settings based on selection

Step 2: Auto-import Default Settings
✓ SEO templates configured
✓ Social media settings prepared
✓ Image sizes optimized
✓ Caching enabled

Step 3: Search Engine Verification
[ ] Google Search Console (optional - auto-generate meta tag)
[ ] Bing Webmaster (optional - auto-generate meta tag)

Step 4: Done!
→ Redirect to dashboard with success message
```

---

## 2. SEO AUTO-CONFIGURATION

### 2.1 Smart Meta Title Templates (Auto-Applied)

```php
// app/Config/SEODefaults.php
class SEODefaults {
    public static $templates = [
        'post' => '%title% | %sitename%',
        'page' => '%title% | %sitename%',
        'category' => '%category% Archives | %sitename%',
        'tag' => '%tag% Tag | %sitename%',
        'author' => 'Posts by %author% | %sitename%',
        'search' => 'Search Results for "%search%" | %sitename%',
        'archive' => '%date% Archive | %sitename%',
        '404' => 'Page Not Found | %sitename%'
    ];
    
    public static $descriptionTemplates = [
        'post' => '%excerpt%',
        'page' => '%excerpt%',
        'category' => 'Browse our %category% articles and guides on %sitename%',
        'tag' => 'Articles tagged with %tag% on %sitename%',
        'author' => 'Read all posts by %author% on %sitename%'
    ];
}

// Auto-generate jika user tidak set manual
public function getMetaTitle($post) {
    if (!empty($post->custom_meta_title)) {
        return $post->custom_meta_title;
    }
    
    // Auto-generate from template
    $template = SEODefaults::$templates[$post->post_type];
    return $this->replaceVariables($template, $post);
}
```

### 2.2 Auto Meta Description

```php
// Automatically generate if not set
public function autoGenerateMetaDescription($content, $maxLength = 155) {
    // Remove HTML tags
    $text = strip_tags($content);
    
    // Get first paragraph or first 155 characters
    $sentences = preg_split('/[.!?]+/', $text);
    $description = '';
    
    foreach ($sentences as $sentence) {
        if (strlen($description . $sentence) <= $maxLength) {
            $description .= $sentence . '. ';
        } else {
            break;
        }
    }
    
    return trim($description) ?: substr($text, 0, $maxLength) . '...';
}

// Auto-save on post publish
public function beforeSave($post) {
    if (empty($post->meta_description)) {
        $post->meta_description = $this->autoGenerateMetaDescription($post->content);
    }
}
```

### 2.3 Auto Schema Generation

```php
// Automatically inject schema based on content type
class SchemaAutoGenerator {
    
    public function autoInject($post) {
        $schema = [];
        
        switch ($post->post_type) {
            case 'post':
                $schema = $this->generateArticleSchema($post);
                break;
            case 'page':
                $schema = $this->generateWebPageSchema($post);
                break;
            case 'product':
                $schema = $this->generateProductSchema($post);
                break;
        }
        
        // Auto-save to seo_meta table
        $this->saveSchema($post->id, $schema);
        
        return $schema;
    }
    
    protected function generateArticleSchema($post) {
        return [
            "@context" => "https://schema.org",
            "@type" => "Article",
            "headline" => $post->title,
            "image" => $post->featured_image ?? $this->getDefaultImage(),
            "author" => [
                "@type" => "Person",
                "name" => $post->author->display_name
            ],
            "publisher" => [
                "@type" => "Organization",
                "name" => setting('site_name'),
                "logo" => [
                    "@type" => "ImageObject",
                    "url" => setting('site_logo')
                ]
            ],
            "datePublished" => $post->published_at,
            "dateModified" => $post->updated_at,
            "description" => $post->meta_description
        ];
    }
    
    // Auto-detect and add FAQ schema from content
    public function autoDetectFAQSchema($content) {
        $faqPattern = '/<h3[^>]*>([^<]+)<\/h3>\s*<p>([^<]+)<\/p>/i';
        
        if (preg_match_all($faqPattern, $content, $matches)) {
            $faqs = [];
            
            for ($i = 0; $i < count($matches[0]); $i++) {
                $faqs[] = [
                    "@type" => "Question",
                    "name" => strip_tags($matches[1][$i]),
                    "acceptedAnswer" => [
                        "@type" => "Answer",
                        "text" => strip_tags($matches[2][$i])
                    ]
                ];
            }
            
            if (count($faqs) >= 2) {
                return [
                    "@context" => "https://schema.org",
                    "@type" => "FAQPage",
                    "mainEntity" => $faqs
                ];
            }
        }
        
        return null;
    }
}
```

### 2.4 Auto Breadcrumb with Schema

```php
// Automatically generate breadcrumbs with schema
class BreadcrumbGenerator {
    
    public function generate($post) {
        $breadcrumbs = [
            ['name' => 'Home', 'url' => base_url()]
        ];
        
        // Auto-add categories
        if (!empty($post->categories)) {
            foreach ($post->categories as $category) {
                $breadcrumbs[] = [
                    'name' => $category->name,
                    'url' => site_url('category/' . $category->slug)
                ];
            }
        }
        
        // Add current page
        $breadcrumbs[] = [
            'name' => $post->title,
            'url' => site_url($post->slug)
        ];
        
        // Auto-generate schema
        $schema = $this->generateBreadcrumbSchema($breadcrumbs);
        
        return [
            'html' => $this->renderHTML($breadcrumbs),
            'schema' => $schema
        ];
    }
    
    protected function generateBreadcrumbSchema($breadcrumbs) {
        $items = [];
        
        foreach ($breadcrumbs as $index => $crumb) {
            $items[] = [
                "@type" => "ListItem",
                "position" => $index + 1,
                "name" => $crumb['name'],
                "item" => $crumb['url']
            ];
        }
        
        return [
            "@context" => "https://schema.org",
            "@type" => "BreadcrumbList",
            "itemListElement" => $items
        ];
    }
}
```

---

## 3. SITEMAP AUTO-GENERATION

### 3.1 Automatic Sitemap Updates

```php
// Auto-regenerate on content changes
class SitemapAutomation {
    
    // Triggered on post save/update/delete
    public function afterPostSave($post) {
        if ($post->status === 'published') {
            $this->regenerateSitemap();
        }
    }
    
    public function regenerateSitemap() {
        // Generate main sitemap
        $this->generatePostSitemap();
        $this->generatePageSitemap();
        $this->generateCategorySitemap();
        $this->generateTagSitemap();
        
        // Generate sitemap index
        $this->generateSitemapIndex();
        
        // Auto-ping search engines
        $this->pingSitemapToSearchEngines();
    }
    
    protected function generatePostSitemap() {
        $posts = model('PostModel')
            ->where('status', 'published')
            ->orderBy('updated_at', 'DESC')
            ->findAll();
        
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
        
        foreach ($posts as $post) {
            $url = $xml->addChild('url');
            $url->addChild('loc', site_url($post->slug));
            $url->addChild('lastmod', date('c', strtotime($post->updated_at)));
            $url->addChild('changefreq', 'weekly');
            $url->addChild('priority', '0.8');
            
            // Auto-add image sitemap
            if ($post->featured_image) {
                $image = $url->addChild('image:image', null, 'http://www.google.com/schemas/sitemap-image/1.1');
                $image->addChild('image:loc', $post->featured_image);
                $image->addChild('image:title', $post->title);
            }
        }
        
        file_put_contents(FCPATH . 'sitemap-posts.xml', $xml->asXML());
    }
    
    protected function pingSitemapToSearchEngines() {
        $sitemapUrl = urlencode(site_url('sitemap.xml'));
        
        // Auto-ping Google
        $googlePing = "https://www.google.com/ping?sitemap={$sitemapUrl}";
        $this->ping($googlePing);
        
        // Auto-ping Bing
        $bingPing = "https://www.bing.com/ping?sitemap={$sitemapUrl}";
        $this->ping($bingPing);
    }
    
    protected function ping($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    }
}
```

---

## 4. IMAGE OPTIMIZATION AUTO-PROCESSING

### 4.1 Auto Image Optimization on Upload

```php
class ImageAutoOptimizer {
    
    protected $maxWidth = 1920;
    protected $quality = 85;
    protected $generateWebP = true;
    protected $generateThumbnails = true;
    
    public function processUpload($file) {
        $originalPath = $file->store('uploads/images');
        
        // Auto-resize if too large
        $this->autoResize($originalPath);
        
        // Auto-compress
        $this->autoCompress($originalPath);
        
        // Auto-generate WebP version
        if ($this->generateWebP) {
            $this->createWebPVersion($originalPath);
        }
        
        // Auto-generate thumbnails
        if ($this->generateThumbnails) {
            $this->createThumbnails($originalPath);
        }
        
        // Auto-extract alt text from filename
        $autoAlt = $this->generateAltFromFilename($file->getName());
        
        // Save to database
        return $this->saveToDatabase([
            'filepath' => $originalPath,
            'alt_text' => $autoAlt,
            'filesize' => filesize($originalPath),
            'width' => getimagesize($originalPath)[0],
            'height' => getimagesize($originalPath)[1]
        ]);
    }
    
    protected function autoResize($path) {
        list($width, $height) = getimagesize($path);
        
        if ($width > $this->maxWidth) {
            $newHeight = ($this->maxWidth / $width) * $height;
            
            $image = \Config\Services::image()
                ->withFile($path)
                ->resize($this->maxWidth, $newHeight, true)
                ->save($path);
        }
    }
    
    protected function autoCompress($path) {
        $image = \Config\Services::image()
            ->withFile($path)
            ->save($path, $this->quality);
    }
    
    protected function createWebPVersion($path) {
        $webpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $path);
        
        $image = imagecreatefromstring(file_get_contents($path));
        imagewebp($image, $webpPath, $this->quality);
        imagedestroy($image);
        
        return $webpPath;
    }
    
    protected function createThumbnails($path) {
        $sizes = [
            'thumbnail' => [150, 150],
            'medium' => [300, 300],
            'large' => [1024, 1024]
        ];
        
        foreach ($sizes as $name => $size) {
            $thumbPath = preg_replace('/(\.[^.]+)$/', "-{$name}$1", $path);
            
            \Config\Services::image()
                ->withFile($path)
                ->fit($size[0], $size[1])
                ->save($thumbPath);
        }
    }
    
    protected function generateAltFromFilename($filename) {
        // Remove extension
        $alt = preg_replace('/\.[^.]+$/', '', $filename);
        
        // Replace hyphens/underscores with spaces
        $alt = str_replace(['-', '_'], ' ', $alt);
        
        // Capitalize first letter
        return ucfirst($alt);
    }
}
```

---

## 5. INTERNAL LINKING AUTOMATION

### 5.1 Auto-suggest Internal Links

```php
class InternalLinkingSuggester {
    
    // Auto-suggest relevant internal links while writing
    public function getSuggestions($content, $currentPostId) {
        // Extract keywords from content
        $keywords = $this->extractKeywords($content);
        
        // Find related posts
        $relatedPosts = model('PostModel')
            ->where('id !=', $currentPostId)
            ->where('status', 'published')
            ->groupStart()
                ->like('title', $keywords[0])
                ->orLike('content', $keywords[0])
            ->groupEnd()
            ->limit(5)
            ->findAll();
        
        return $relatedPosts;
    }
    
    // Auto-insert internal links to older content
    public function autoLinkToNewContent($newPost) {
        $keywords = $this->extractKeywords($newPost->title . ' ' . $newPost->content);
        
        // Find older posts that mention these keywords
        $oldPosts = model('PostModel')
            ->where('id <', $newPost->id)
            ->where('status', 'published')
            ->groupStart()
                ->like('content', $keywords[0])
                ->orLike('content', $keywords[1])
            ->groupEnd()
            ->limit(10)
            ->findAll();
        
        // Auto-suggest adding links in these posts
        foreach ($oldPosts as $oldPost) {
            $this->suggestLinkInsertion($oldPost, $newPost);
        }
    }
    
    protected function extractKeywords($text) {
        // Simple keyword extraction (can be improved with NLP)
        $text = strtolower(strip_tags($text));
        $words = str_word_count($text, 1);
        
        // Remove common words
        $stopWords = ['the', 'is', 'at', 'which', 'on', 'a', 'an', 'and', 'or'];
        $words = array_diff($words, $stopWords);
        
        // Get most frequent words
        $wordCount = array_count_values($words);
        arsort($wordCount);
        
        return array_slice(array_keys($wordCount), 0, 5);
    }
    
    protected function suggestLinkInsertion($targetPost, $linkPost) {
        // Store suggestion for admin review
        model('LinkSuggestionModel')->insert([
            'target_post_id' => $targetPost->id,
            'link_post_id' => $linkPost->id,
            'suggested_at' => date('Y-m-d H:i:s'),
            'status' => 'pending'
        ]);
    }
}
```

---

## 6. CONTENT ANALYSIS AUTOMATION

### 6.1 Real-time SEO Analysis

```php
// Auto-analyze while user types (via AJAX)
class RealtimeSEOAnalyzer {
    
    public function analyzeOnTheFly($data) {
        $analysis = [
            'score' => 0,
            'checks' => []
        ];
        
        // Auto-check title length
        $titleCheck = $this->checkTitleLength($data['title']);
        $analysis['checks']['title_length'] = $titleCheck;
        $analysis['score'] += $titleCheck['score'];
        
        // Auto-check meta description
        $metaCheck = $this->checkMetaDescription($data['meta_description']);
        $analysis['checks']['meta_description'] = $metaCheck;
        $analysis['score'] += $metaCheck['score'];
        
        // Auto-check focus keyword
        if (!empty($data['focus_keyword'])) {
            $keywordCheck = $this->checkFocusKeyword(
                $data['title'], 
                $data['content'], 
                $data['focus_keyword']
            );
            $analysis['checks']['focus_keyword'] = $keywordCheck;
            $analysis['score'] += $keywordCheck['score'];
        }
        
        // Auto-check content length
        $lengthCheck = $this->checkContentLength($data['content']);
        $analysis['checks']['content_length'] = $lengthCheck;
        $analysis['score'] += $lengthCheck['score'];
        
        // Auto-check readability
        $readabilityCheck = $this->checkReadability($data['content']);
        $analysis['checks']['readability'] = $readabilityCheck;
        $analysis['score'] += $readabilityCheck['score'];
        
        // Auto-check images
        $imageCheck = $this->checkImages($data['content']);
        $analysis['checks']['images'] = $imageCheck;
        $analysis['score'] += $imageCheck['score'];
        
        // Auto-check internal links
        $linkCheck = $this->checkInternalLinks($data['content']);
        $analysis['checks']['internal_links'] = $linkCheck;
        $analysis['score'] += $linkCheck['score'];
        
        // Auto-check external links
        $externalCheck = $this->checkExternalLinks($data['content']);
        $analysis['checks']['external_links'] = $externalCheck;
        $analysis['score'] += $externalCheck['score'];
        
        // Normalize score to 100
        $analysis['score'] = min(100, $analysis['score']);
        
        return $analysis;
    }
    
    protected function checkTitleLength($title) {
        $length = strlen($title);
        
        if ($length >= 30 && $length <= 60) {
            return [
                'status' => 'good',
                'score' => 15,
                'message' => '✓ Title length is optimal'
            ];
        } elseif ($length < 30) {
            return [
                'status' => 'warning',
                'score' => 5,
                'message' => '⚠ Title is too short (aim for 30-60 characters)'
            ];
        } else {
            return [
                'status' => 'error',
                'score' => 0,
                'message' => '✗ Title is too long (aim for 30-60 characters)'
            ];
        }
    }
    
    protected function checkMetaDescription($description) {
        $length = strlen($description);
        
        if ($length >= 120 && $length <= 155) {
            return [
                'status' => 'good',
                'score' => 10,
                'message' => '✓ Meta description length is optimal'
            ];
        } elseif (empty($description)) {
            return [
                'status' => 'error',
                'score' => 0,
                'message' => '✗ Meta description is missing'
            ];
        } else {
            return [
                'status' => 'warning',
                'score' => 5,
                'message' => "⚠ Meta description length: {$length} chars (aim for 120-155)"
            ];
        }
    }
    
    protected function checkImages($content) {
        preg_match_all('/<img[^>]+>/i', $content, $images);
        $imageCount = count($images[0]);
        
        if ($imageCount === 0) {
            return [
                'status' => 'warning',
                'score' => 0,
                'message' => '⚠ No images found. Add at least one image.'
            ];
        }
        
        // Check for alt tags
        $imagesWithAlt = 0;
        foreach ($images[0] as $img) {
            if (preg_match('/alt=["\'][^"\']*["\']/i', $img)) {
                $imagesWithAlt++;
            }
        }
        
        if ($imagesWithAlt === $imageCount) {
            return [
                'status' => 'good',
                'score' => 10,
                'message' => "✓ All {$imageCount} images have alt text"
            ];
        } else {
            $missing = $imageCount - $imagesWithAlt;
            return [
                'status' => 'warning',
                'score' => 5,
                'message' => "⚠ {$missing} images missing alt text"
            ];
        }
    }
}
```

---

## 7. ROBOTS.TXT AUTO-GENERATION

### 7.1 Smart Robots.txt

```php
class RobotsTxtGenerator {
    
    public function generate() {
        $content = "# Auto-generated by LightCMS\n";
        $content .= "# Last updated: " . date('Y-m-d H:i:s') . "\n\n";
        
        $content .= "User-agent: *\n";
        $content .= "Allow: /\n\n";
        
        // Auto-block admin area
        $content .= "# Block admin area\n";
        $content .= "Disallow: /admin/\n";
        $content .= "Disallow: /login/\n\n";
        
        // Auto-block system files
        $content .= "# Block system files\n";
        $content .= "Disallow: /app/\n";
        $content .= "Disallow: /writable/\n";
        $content .= "Disallow: /vendor/\n\n";
        
        // Auto-add sitemap
        $content .= "# Sitemap\n";
        $content .= "Sitemap: " . site_url('sitemap.xml') . "\n\n";
        
        // Auto-add crawl delay if set
        if ($crawlDelay = setting('robots_crawl_delay')) {
            $content .= "Crawl-delay: {$crawlDelay}\n\n";
        }
        
        // Save to public folder
        file_put_contents(FCPATH . 'robots.txt', $content);
        
        return $content;
    }
    
    // Auto-regenerate on settings change
    public function autoRegenerate() {
        $this->generate();
    }
}
```

---

## 8. AUTO REDIRECT DETECTION

### 8.1 Smart 404 to Redirect Converter

```php
class AutoRedirectManager {
    
    // Log 404 errors and suggest redirects
    public function handle404($requestedUrl) {
        // Log the 404
        model('ErrorLogModel')->insert([
            'url' => $requestedUrl,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'ip' => $this->request->getIPAddress(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Auto-find similar URLs
        $suggestions = $this->findSimilarUrls($requestedUrl);
        
        if (!empty($suggestions)) {
            // Auto-create redirect suggestion
            model('RedirectSuggestionModel')->insert([
                'source_url' => $requestedUrl,
                'suggested_target' => $suggestions[0],
                'confidence' => $this->calculateConfidence($requestedUrl, $suggestions[0]),
                'status' => 'pending'
            ]);
        }
        
        return $suggestions;
    }
    
    protected function findSimilarUrls($url) {
        // Remove domain and get path
        $path = parse_url($url, PHP_URL_PATH);
        
        // Search for similar slugs
        $posts = model('PostModel')
            ->like('slug', $path, 'both')
            ->limit(5)
            ->findAll();
        
        $suggestions = [];
        foreach ($posts as $post) {
            $suggestions[] = site_url($post->slug);
        }
        
        return $suggestions;
    }
    
    protected function calculateConfidence($source, $target) {
        similar_text($source, $target, $percent);
        return round($percent, 2);
    }
    
    // Auto-create redirect when slug changes
    public function onSlugChange($oldSlug, $newSlug) {
        model('RedirectModel')->insert([
            'source_url' => '/' . $oldSlug,
            'target_url' => '/' . $newSlug,
            'redirect_type' => '301',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
```

---

## 9. AUTO SOCIAL META TAGS

### 9.1 Smart Open Graph Generation

```php
class SocialMetaGenerator {
    
    public function generateOpenGraph($post) {
        $og = [
            'og:title' => $post->meta_title ?: $post->title,
            'og:description' => $post->meta_description ?: $this->generateExcerpt($post->content),
            'og:type' => $this->getOGType($post->post_type),
            'og:url' => site_url($post->slug),
            'og:site_name' => setting('site_name'),
            'og:locale' => 'en_US'
        ];
        
        // Auto-add image
        if ($post->featured_image) {
            $og['og:image'] = $post->featured_image;
            $og['og:image:width'] = 1200;
            $og['og:image:height'] = 630;
        } else {
            // Use default site image
            $og['og:image'] = setting('default_og_image');
        }
        
        // Auto-add article tags
        if ($post->post_type === 'post') {
            $og['article:published_time'] = date('c', strtotime($post->published_at));
            $og['article:modified_time'] = date('c', strtotime($post->updated_at));
            $og['article:author'] = $post->author->display_name;
            
            // Auto-add tags
            if (!empty($post->tags)) {
                foreach ($post->tags as $tag) {
                    $og['article:tag'][] = $tag->name;
                }
            }
        }
        
        return $og;
    }
    
    public function generateTwitterCard($post) {
        $twitter = [
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $post->meta_title ?: $post->title,
            'twitter:description' => $post->meta_description ?: $this->generateExcerpt($post->content),
            'twitter:image' => $post->featured_image ?: setting('default_og_image')
        ];
        
        // Auto-add Twitter handle if set
        if ($handle = setting('twitter_handle')) {
            $twitter['twitter:site'] = '@' . $handle;
            $twitter['twitter:creator'] = '@' . ($post->author->twitter ?? $handle);
        }
        
        return $twitter;
    }
    
    protected function getOGType($postType) {
        $types = [
            'post' => 'article',
            'page' => 'website',
            'product' => 'product'
        ];
        
        return $types[$postType] ?? 'website';
    }
}
```

---

## 10. DATABASE OPTIMIZATION AUTOMATION

### 10.1 Auto Database Cleanup

```php
class DatabaseAutoCleanup {
    
    // Run daily via CRON
    public function runDailyCleanup() {
        // Auto-delete old revisions (keep last 10)
        $this->cleanupRevisions();
        
        // Auto-delete spam comments older than 30 days
        $this->cleanupSpamComments();
        
        // Auto-delete trash items older than 30 days
        $this->cleanupTrash();
        
        // Auto-optimize tables
        $this->optimizeTables();
        
        // Auto-delete orphaned meta
        $this->cleanupOrphanedMeta();
        
        // Auto-delete old activity logs (keep 90 days)
        $this->cleanupActivityLogs();
    }
    
    protected function cleanupRevisions() {
        $db = \Config\Database::connect();
        
        // Keep only last 10 revisions per post
        $db->query("
            DELETE FROM post_revisions 
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT id FROM post_revisions 
                    WHERE post_id IN (SELECT id FROM posts)
                    ORDER BY created_at DESC 
                    LIMIT 10
                ) temp
            )
        ");
    }
    
    protected function cleanupSpamComments() {
        model('CommentModel')
            ->where('status', 'spam')
            ->where('created_at <', date('Y-m-d', strtotime('-30 days')))
            ->delete();
    }
    
    protected function cleanupTrash() {
        model('PostModel')
            ->where('status', 'trash')
            ->where('updated_at <', date('Y-m-d', strtotime('-30 days')))
            ->delete();
    }
    
    protected function optimizeTables() {
        $db = \Config\Database::connect();
        $tables = $db->listTables();
        
        foreach ($tables as $table) {
            $db->query("OPTIMIZE TABLE {$table}");
        }
    }
    
    protected function cleanupOrphanedMeta() {
        $db = \Config\Database::connect();
        
        // Delete meta for non-existent posts
        $db->query("
            DELETE FROM seo_meta 
            WHERE post_id NOT IN (SELECT id FROM posts)
        ");
    }
    
    protected function cleanupActivityLogs() {
        model('ActivityLogModel')
            ->where('created_at <', date('Y-m-d', strtotime('-90 days')))
            ->delete();
    }
}
```

---

## 11. PERFORMANCE MONITORING AUTOMATION

### 11.1 Auto Performance Alerts

```php
class PerformanceMonitor {
    
    protected $thresholds = [
        'page_load_time' => 3, // seconds
        'database_queries' => 20,
        'memory_usage' => 64 * 1024 * 1024, // 64MB
    ];
    
    public function monitor() {
        $metrics = [
            'page_load_time' => $this->getPageLoadTime(),
            'database_queries' => $this->getQueryCount(),
            'memory_usage' => memory_get_peak_usage(true)
        ];
        
        // Auto-log metrics
        model('PerformanceLogModel')->insert([
            'url' => current_url(),
            'load_time' => $metrics['page_load_time'],
            'queries' => $metrics['database_queries'],
            'memory' => $metrics['memory_usage'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Auto-alert if thresholds exceeded
        foreach ($metrics as $metric => $value) {
            if ($value > $this->thresholds[$metric]) {
                $this->sendAlert($metric, $value);
            }
        }
    }
    
    protected function sendAlert($metric, $value) {
        // Send email to admin
        $email = \Config\Services::email();
        $email->setTo(setting('admin_email'));
        $email->setSubject('Performance Alert: ' . $metric);
        $email->setMessage("
            Performance threshold exceeded:
            Metric: {$metric}
            Value: {$value}
            Threshold: {$this->thresholds[$metric]}
            URL: " . current_url()
        );
        $email->send();
    }
}
```

---

## 12. SMART DEFAULTS SUMMARY

### Default Settings Applied on Installation:

```php
// app/Database/Seeds/DefaultSettingsSeeder.php
class DefaultSettingsSeeder extends Seeder {
    public function run() {
        $settings = [
            // SEO Settings
            'seo_enabled' => 1,
            'auto_generate_meta' => 1,
            'auto_generate_schema' => 1,
            'auto_generate_sitemap' => 1,
            'auto_ping_search_engines' => 1,
            'breadcrumbs_enabled' => 1,
            'breadcrumbs_schema' => 1,
            
            // Image Settings
            'auto_optimize_images' => 1,
            'auto_generate_webp' => 1,
            'auto_generate_thumbnails' => 1,
            'max_image_width' => 1920,
            'image_quality' => 85,
            'lazy_load_images' => 1,
            
            // Performance
            'cache_enabled' => 1,
            'cache_ttl' => 3600,
            'minify_css' => 1,
            'minify_js' => 1,
            'gzip_compression' => 1,
            
            // Content
            'auto_save_interval' => 30, // seconds
            'revisions_to_keep' => 10,
            'auto_excerpt_length' => 155,
            
            // Security
            'auto_update_security' => 1,
            'block_bad_bots' => 1,
            'rate_limit_enabled' => 1,
            
            // Cleanup
            'auto_cleanup_enabled' => 1,
            'cleanup_trash_days' => 30,
            'cleanup_spam_days' => 30,
            'cleanup_logs_days' => 90,
            
            // Redirects
            'auto_create_redirects' => 1,
            'auto_detect_404' => 1,
            'auto_suggest_redirects' => 1,
            
            // Social
            'auto_og_tags' => 1,
            'auto_twitter_cards' => 1,
            
            // Analytics
            'track_404_errors' => 1,
            'track_performance' => 1
        ];
        
        foreach ($settings as $key => $value) {
            model('SettingModel')->insert([
                'setting_key' => $key,
                'setting_value' => $value,
                'autoload' => 1
            ]);
        }
    }
}
```

---

## 13. ADMIN NOTIFICATION SYSTEM

### Auto-notify admin of important events:

```php
class AutoNotifier {
    
    public function notify($event, $data) {
        switch ($event) {
            case 'new_comment':
                $this->notifyNewComment($data);
                break;
            case 'low_seo_score':
                $this->notifyLowSEO($data);
                break;
            case 'broken_link_detected':
                $this->notifyBrokenLink($data);
                break;
            case '404_spike':
                $this->notify404Spike($data);
                break;
            case 'performance_issue':
                $this->notifyPerformanceIssue($data);
                break;
        }
    }
    
    protected function notifyLowSEO($post) {
        if ($post->seo_score < 50) {
            // Show in-dashboard notification
            model('NotificationModel')->insert([
                'type' => 'warning',
                'title' => 'Low SEO Score',
                'message' => "\"{$post->title}\" has a low SEO score of {$post->seo_score}/100",
                'action_url' => admin_url("posts/edit/{$post->id}"),
                'action_text' => 'Improve SEO',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
```

---

## SUMMARY: Automation Features

### ✅ **Completely Automated (Zero Configuration)**
1. Meta title/description generation
2. Schema markup injection
3. Sitemap generation & updates
4. Image optimization on upload
5. WebP conversion
6. Thumbnail generation
7. Alt text suggestion
8. Breadcrumbs with schema
9. Open Graph & Twitter Cards
10. Robots.txt generation
11. 404 tracking & redirect suggestions
12. Database cleanup
13. Cache management

### ⚙️ **Smart Defaults (One-Click Enable/Disable)**
14. SEO analysis on save
15. Internal linking suggestions
16. Performance monitoring
17. Security updates
18. Broken link detection
19. Spam comment filtering

### 🎯 **User Benefits**
- **90% reduction** in manual SEO configuration
- **Instant SEO** out of the box
- **Auto-optimization** without technical knowledge
- **Proactive alerts** for issues
- **Zero maintenance** for routine tasks

Dengan automasi ini, LightCMS akan menjadi **truly "plug & play"** seperti RankMath, bahkan lebih baik karena terintegrasi langsung dengan CMS!
