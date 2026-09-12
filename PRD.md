# PRD: LightCMS - Lightweight Content Management System

## 1. OVERVIEW

### 1.1 Product Vision
LightCMS adalah CMS berbasis CodeIgniter 4 yang ultra-ringan, hemat resource, dengan fitur SEO lengkap setara RankMath dan fleksibilitas theme seperti WordPress.

### 1.2 Core Principles
- **Ultra Lightweight**: Maksimal penggunaan RAM 50MB
- **SEO-First**: SEO tools comprehensive built-in
- **Theme Flexibility**: Hot-swappable themes dengan customization
- **Performance**: Page load < 1 detik
- **Developer Friendly**: Clean code, well-documented

---

## 2. TECHNICAL STACK

### 2.1 Backend
- **Framework**: CodeIgniter 4.4+
- **PHP Version**: 8.1+
- **Database**: MySQL 8.0+ / MariaDB 10.6+
- **Cache**: File-based / Redis (optional)
- **Session**: File-based / Database

### 2.2 Frontend
- **Core**: Vanilla JavaScript (no jQuery)
- **CSS Framework**: Custom lightweight CSS (<50KB)
- **Editor**: Custom block editor (inspired by Gutenberg)
- **Icons**: SVG sprite system

### 2.3 Performance Optimization
- Lazy loading autoloader
- Database query optimization dengan query builder
- Asset minification & compression
- CDN ready
- Browser caching strategy

---

## 3. CORE FEATURES

## 3.1 SEO MODULE (RankMath Equivalent)

### A. On-Page SEO

#### 1. **Meta Management**
```php
- SEO Title (dengan template variables)
- Meta Description (dengan character counter)
- Meta Keywords
- Canonical URL
- Meta Robots (index/noindex, follow/nofollow)
- Open Graph Tags (FB, LinkedIn)
- Twitter Cards
- Schema.org markup generator
```

#### 2. **Content Analysis**
```php
- Focus Keyword Analysis
- Keyword Density Checker
- Readability Score (Flesch Reading Ease)
- Content Length Analyzer
- Heading Structure (H1-H6) validation
- Internal/External Link counter
- Image Alt Text checker
- Broken link detector
```

#### 3. **SEO Score System**
```php
- Real-time SEO score (0-100)
- Traffic light indicator (Red/Yellow/Green)
- Actionable suggestions
- Competitor comparison (optional)
```

#### 4. **Advanced SEO Features**
```php
- Breadcrumb schema generator
- FAQ schema builder
- Article/Blog schema
- Product schema (for e-commerce)
- Local Business schema
- Automatic internal linking suggestions
- Content AI suggestions (keyword placement)
```

### B. Technical SEO

#### 1. **XML Sitemap Generator**
```php
- Auto-generate sitemap.xml
- Sitemap index for large sites
- Image sitemap
- Video sitemap
- Custom post type sitemaps
- Sitemap split (max 50k URLs)
- Last modified auto-update
- Priority & change frequency settings
```

#### 2. **Robots.txt Manager**
```php
- Visual robots.txt editor
- Syntax validator
- Crawl delay settings
- Sitemap reference auto-add
```

#### 3. **Structured Data**
```php
- JSON-LD generator
- Schema template library
- Custom schema builder
- Schema validator integration
- Auto-inject to pages
```

#### 4. **Redirect Manager**
```php
- 301/302/307 redirects
- Bulk redirect import
- Redirect logs
- Broken link redirects
- Regex pattern support
- Redirect chains detector
```

#### 5. **Performance SEO**
```php
- Lazy load images
- Critical CSS generator
- Asset minification
- GZIP compression
- Browser caching headers
- CDN integration
```

### C. Analytics & Monitoring

```php
- Google Search Console integration
- Google Analytics 4 integration
- Keyword ranking tracker
- Backlink monitor
- 404 error logger
- Crawl error alerts
- SEO audit scheduler
```

---

## 3.2 CONTENT EDITOR (WordPress-like)

### A. Block Editor

#### 1. **Core Blocks**
```javascript
- Paragraph
- Heading (H1-H6)
- Image (dengan lazy load, alt, caption)
- Gallery
- Video (embed & upload)
- Audio
- Quote/Blockquote
- Code block (syntax highlighting)
- Table
- List (ordered/unordered)
- Button
- Separator/Divider
- Spacer
- Columns/Grid
- Embed (YouTube, Vimeo, Twitter, Instagram)
```

#### 2. **Advanced Blocks**
```javascript
- Accordion/FAQ
- Tabs
- Call-to-Action
- Testimonial
- Pricing Table
- Progress Bar
- Countdown Timer
- Maps (Google Maps embed)
- Contact Form
- Social Share
```

#### 3. **Editor Features**
```javascript
- Drag & drop block reordering
- Block duplication
- Block templates/patterns
- Reusable blocks library
- Undo/Redo
- Autosave (setiap 30 detik)
- Revision history
- Distraction-free mode
- Code editor mode (HTML)
- Preview mode (desktop/tablet/mobile)
- Word counter
- Reading time estimator
```

#### 4. **Media Management**
```javascript
- Drag & drop upload
- Bulk upload
- Image optimization (auto-resize)
- WebP conversion
- Thumbnail generation
- Media library filter/search
- Image editing (crop, rotate, flip)
- Alt text batch editor
- Unused media detector
```

---

## 3.3 THEME SYSTEM

### A. Theme Architecture

#### 1. **Directory Structure**
```
/themes/
  ├── default/
  │   ├── assets/
  │   │   ├── css/
  │   │   ├── js/
  │   │   └── images/
  │   ├── layouts/
  │   │   ├── header.php
  │   │   ├── footer.php
  │   │   ├── sidebar.php
  │   │   └── main.php
  │   ├── templates/
  │   │   ├── home.php
  │   │   ├── single.php
  │   │   ├── page.php
  │   │   ├── archive.php
  │   │   ├── category.php
  │   │   └── 404.php
  │   ├── functions.php
  │   ├── theme.json (config)
  │   └── screenshot.png
```

#### 2. **theme.json Configuration**
```json
{
  "name": "Default Theme",
  "version": "1.0.0",
  "author": "LightCMS",
  "description": "Lightweight default theme",
  "support": {
    "widgets": true,
    "menus": true,
    "custom_logo": true,
    "custom_colors": true,
    "custom_fonts": true
  },
  "settings": {
    "layout": "boxed|full-width",
    "sidebar_position": "left|right|none",
    "primary_color": "#3498db",
    "secondary_color": "#2ecc71"
  },
  "menus": {
    "primary": "Primary Menu",
    "footer": "Footer Menu"
  },
  "widget_areas": {
    "sidebar": "Main Sidebar",
    "footer_1": "Footer Column 1",
    "footer_2": "Footer Column 2"
  }
}
```

#### 3. **Theme Functions (functions.php)**
```php
<?php
// Register menus
Theme::registerMenus([
    'primary' => 'Primary Menu',
    'footer' => 'Footer Menu'
]);

// Register widget areas
Theme::registerWidgetArea('sidebar', [
    'name' => 'Main Sidebar',
    'description' => 'Widgets in this area will be shown on all posts and pages.'
]);

// Add theme support
Theme::addSupport('custom-logo');
Theme::addSupport('post-thumbnails');
Theme::addSupport('custom-background');

// Enqueue scripts & styles
Theme::enqueueStyle('main', 'assets/css/style.css');
Theme::enqueueScript('main', 'assets/js/main.js');

// Custom functions
function getRelatedPosts($postId, $limit = 5) {
    // Implementation
}
```

### B. Theme Customizer

#### 1. **Live Customizer Features**
```php
- Logo upload & management
- Color scheme picker
  - Primary color
  - Secondary color
  - Text color
  - Background color
- Typography settings
  - Font family (Google Fonts integration)
  - Font size
  - Line height
  - Letter spacing
- Layout options
  - Site width
  - Sidebar position
  - Header style
  - Footer style
- Custom CSS editor
- Widget management (drag & drop)
- Menu builder (drag & drop)
- Real-time preview
```

#### 2. **Theme Options Panel**
```php
- Homepage settings
  - Static page / Latest posts
  - Featured content
- Blog settings
  - Posts per page
  - Excerpt length
  - Show/hide author, date, categories
- Header settings
  - Sticky header
  - Search bar
  - Social icons
- Footer settings
  - Copyright text
  - Footer widgets
  - Back to top button
```

### C. Theme Marketplace Features

```php
- One-click theme installation
- Theme preview (screenshot + demo)
- Theme update checker
- Export/Import theme settings
- Child theme support
- Theme compatibility checker
```

---

## 3.4 CONTENT MANAGEMENT

### A. Post Types

```php
1. Posts (Blog articles)
2. Pages (Static pages)
3. Media (Images, videos, files)
4. Custom Post Types (user-definable)
```

### B. Taxonomy System

```php
- Categories (hierarchical)
- Tags (non-hierarchical)
- Custom taxonomies
```

### C. Content Features

```php
- Draft/Published/Scheduled status
- Featured image
- Excerpt
- Comments system (with moderation)
- Author management
- Bulk actions
- Quick edit
- Trash & restore
- Duplicate post
```

---

## 3.5 USER MANAGEMENT

### A. User Roles

```php
1. Super Admin
   - Full system access

2. Administrator
   - Content management
   - User management
   - Settings (limited)

3. Editor
   - Publish/edit all content
   - Moderate comments

4. Author
   - Publish/edit own content

5. Contributor
   - Submit content for review

6. Subscriber
   - Read-only access
```

### B. User Features

```php
- Profile management
- Avatar upload
- Activity logs
- Two-factor authentication (2FA)
- Password strength enforcer
- Session management
- Role-based permissions
```

---

## 3.6 PERFORMANCE OPTIMIZATION

### A. Caching Strategy

```php
1. Database Query Cache
   - Model-level caching
   - TTL: 3600s (configurable)

2. Page Cache
   - Full page caching for guests
   - Cache invalidation on content update

3. Object Cache
   - Settings cache
   - Menu cache
   - Widget cache

4. Asset Cache
   - CSS/JS versioning
   - Browser cache headers
```

### B. Resource Optimization

```php
- Autoloader optimization (classmap)
- Eager loading relationships
- Pagination (max 50 items)
- Database indexing strategy
- Lazy load images/iframes
- Asset minification
- GZIP compression
- Critical CSS inline
- Defer non-critical JavaScript
```

### C. Memory Management

```php
- Limit: max 50MB RAM usage
- Batch processing for bulk operations
- Generator usage for large datasets
- Memory profiling tools
- Automatic cleanup of temp files
```

---

## 4. DATABASE SCHEMA

### 4.1 Core Tables

```sql
-- Users
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(60) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    display_name VARCHAR(100),
    role_id INT,
    avatar VARCHAR(255),
    status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
    created_at DATETIME,
    updated_at DATETIME,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Roles
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    permissions JSON,
    created_at DATETIME
) ENGINE=InnoDB;

-- Posts
CREATE TABLE posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT,
    excerpt TEXT,
    author_id INT,
    post_type VARCHAR(20) DEFAULT 'post',
    status ENUM('draft', 'published', 'scheduled', 'trash') DEFAULT 'draft',
    featured_image VARCHAR(255),
    comment_status ENUM('open', 'closed') DEFAULT 'open',
    view_count INT DEFAULT 0,
    published_at DATETIME,
    created_at DATETIME,
    updated_at DATETIME,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_type (post_type),
    INDEX idx_author (author_id),
    INDEX idx_published (published_at),
    FULLTEXT idx_search (title, content)
) ENGINE=InnoDB;

-- SEO Meta
CREATE TABLE seo_meta (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT UNIQUE,
    meta_title VARCHAR(255),
    meta_description VARCHAR(320),
    meta_keywords VARCHAR(255),
    canonical_url VARCHAR(255),
    robots_index BOOLEAN DEFAULT 1,
    robots_follow BOOLEAN DEFAULT 1,
    og_title VARCHAR(255),
    og_description VARCHAR(320),
    og_image VARCHAR(255),
    twitter_card VARCHAR(50),
    schema_data JSON,
    focus_keyword VARCHAR(100),
    seo_score TINYINT DEFAULT 0,
    created_at DATETIME,
    updated_at DATETIME,
    INDEX idx_post (post_id)
) ENGINE=InnoDB;

-- Categories
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    parent_id INT DEFAULT 0,
    meta_title VARCHAR(255),
    meta_description VARCHAR(320),
    created_at DATETIME,
    INDEX idx_slug (slug),
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB;

-- Tags
CREATE TABLE tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at DATETIME,
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

-- Post Categories (pivot)
CREATE TABLE post_categories (
    post_id INT,
    category_id INT,
    PRIMARY KEY (post_id, category_id),
    INDEX idx_post (post_id),
    INDEX idx_category (category_id)
) ENGINE=InnoDB;

-- Post Tags (pivot)
CREATE TABLE post_tags (
    post_id INT,
    tag_id INT,
    PRIMARY KEY (post_id, tag_id),
    INDEX idx_post (post_id),
    INDEX idx_tag (tag_id)
) ENGINE=InnoDB;

-- Media
CREATE TABLE media (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(255) NOT NULL,
    filetype VARCHAR(50),
    filesize INT,
    width INT,
    height INT,
    alt_text VARCHAR(255),
    caption TEXT,
    uploaded_by INT,
    created_at DATETIME,
    INDEX idx_type (filetype),
    INDEX idx_uploader (uploaded_by)
) ENGINE=InnoDB;

-- Comments
CREATE TABLE comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    parent_id INT DEFAULT 0,
    author_name VARCHAR(100),
    author_email VARCHAR(100),
    author_ip VARCHAR(45),
    content TEXT,
    status ENUM('pending', 'approved', 'spam', 'trash') DEFAULT 'pending',
    created_at DATETIME,
    INDEX idx_post (post_id),
    INDEX idx_status (status),
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB;

-- Settings
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value LONGTEXT,
    autoload BOOLEAN DEFAULT 1,
    INDEX idx_key (setting_key),
    INDEX idx_autoload (autoload)
) ENGINE=InnoDB;

-- Redirects
CREATE TABLE redirects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    source_url VARCHAR(255) NOT NULL,
    target_url VARCHAR(255) NOT NULL,
    redirect_type ENUM('301', '302', '307') DEFAULT '301',
    hits INT DEFAULT 0,
    is_regex BOOLEAN DEFAULT 0,
    status BOOLEAN DEFAULT 1,
    created_at DATETIME,
    INDEX idx_source (source_url)
) ENGINE=InnoDB;

-- Activity Logs
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100),
    entity_type VARCHAR(50),
    entity_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Menu
CREATE TABLE menus (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    location VARCHAR(50),
    created_at DATETIME
) ENGINE=InnoDB;

-- Menu Items
CREATE TABLE menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    menu_id INT NOT NULL,
    parent_id INT DEFAULT 0,
    title VARCHAR(100),
    url VARCHAR(255),
    target VARCHAR(20),
    css_class VARCHAR(100),
    position INT DEFAULT 0,
    created_at DATETIME,
    INDEX idx_menu (menu_id),
    INDEX idx_parent (parent_id),
    INDEX idx_position (position)
) ENGINE=InnoDB;

-- Themes
CREATE TABLE themes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    version VARCHAR(20),
    author VARCHAR(100),
    is_active BOOLEAN DEFAULT 0,
    settings JSON,
    created_at DATETIME,
    updated_at DATETIME
) ENGINE=InnoDB;
```

---

## 5. API ARCHITECTURE

### 5.1 RESTful API Endpoints

```php
// Content API
GET    /api/posts              // List posts
GET    /api/posts/{id}         // Get single post
POST   /api/posts              // Create post
PUT    /api/posts/{id}         // Update post
DELETE /api/posts/{id}         // Delete post

// SEO API
GET    /api/seo/analyze/{id}   // SEO analysis
POST   /api/seo/meta/{id}      // Update SEO meta
GET    /api/seo/sitemap        // Generate sitemap
GET    /api/seo/score/{id}     // Get SEO score

// Media API
GET    /api/media              // List media
POST   /api/media/upload       // Upload file
DELETE /api/media/{id}         // Delete media

// Theme API
GET    /api/themes             // List themes
POST   /api/themes/activate    // Activate theme
GET    /api/themes/settings    // Get theme settings
POST   /api/themes/settings    // Save theme settings
```

---

## 6. SYSTEM ARCHITECTURE

### 6.1 Directory Structure

```
lightcms/
├── app/
│   ├── Config/
│   │   ├── Routes.php
│   │   ├── Database.php
│   │   └── LightCMS.php
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── Dashboard.php
│   │   │   ├── PostController.php
│   │   │   ├── MediaController.php
│   │   │   ├── SEOController.php
│   │   │   ├── ThemeController.php
│   │   │   └── SettingsController.php
│   │   └── Frontend/
│   │       ├── HomeController.php
│   │       └── PostController.php
│   ├── Models/
│   │   ├── PostModel.php
│   │   ├── UserModel.php
│   │   ├── SEOModel.php
│   │   ├── MediaModel.php
│   │   └── ThemeModel.php
│   ├── Libraries/
│   │   ├── SEO/
│   │   │   ├── Analyzer.php
│   │   │   ├── SchemaGenerator.php
│   │   │   ├── SitemapGenerator.php
│   │   │   └── MetaBuilder.php
│   │   ├── Theme/
│   │   │   ├── ThemeEngine.php
│   │   │   ├── TemplateLoader.php
│   │   │   └── AssetManager.php
│   │   ├── Editor/
│   │   │   ├── BlockRenderer.php
│   │   │   └── BlockParser.php
│   │   └── Cache/
│   │       ├── CacheManager.php
│   │       └── QueryCache.php
│   ├── Helpers/
│   │   ├── seo_helper.php
│   │   ├── theme_helper.php
│   │   └── content_helper.php
│   └── Views/
│       ├── admin/
│       │   ├── layout.php
│       │   ├── dashboard.php
│       │   ├── posts/
│       │   ├── media/
│       │   ├── seo/
│       │   └── settings/
│       └── errors/
├── public/
│   ├── assets/
│   │   ├── admin/
│   │   │   ├── css/
│   │   │   ├── js/
│   │   │   └── images/
│   │   └── frontend/
│   ├── uploads/
│   │   ├── 2024/
│   │   └── cache/
│   ├── themes/
│   └── index.php
├── writable/
│   ├── cache/
│   ├── logs/
│   └── session/
└── vendor/
```

### 6.2 Core Classes

```php
// app/Libraries/SEO/Analyzer.php
<?php
namespace App\Libraries\SEO;

class Analyzer {
    protected $content;
    protected $focusKeyword;
    protected $score = 0;
    protected $suggestions = [];

    public function analyze($content, $focusKeyword) {
        $this->content = $content;
        $this->focusKeyword = $focusKeyword;

        $this->checkKeywordInTitle();
        $this->checkKeywordDensity();
        $this->checkContentLength();
        $this->checkHeadingStructure();
        $this->checkImageAltTags();
        $this->checkInternalLinks();
        $this->checkReadability();

        return [
            'score' => $this->score,
            'suggestions' => $this->suggestions
        ];
    }

    protected function checkKeywordInTitle() {
        // Implementation
        if (stripos($this->content['title'], $this->focusKeyword) !== false) {
            $this->score += 15;
        } else {
            $this->suggestions[] = [
                'type' => 'error',
                'message' => 'Focus keyword not found in title'
            ];
        }
    }

    protected function checkKeywordDensity() {
        $wordCount = str_word_count(strip_tags($this->content['body']));
        $keywordCount = substr_count(
            strtolower($this->content['body']),
            strtolower($this->focusKeyword)
        );
        $density = ($keywordCount / $wordCount) * 100;

        if ($density >= 0.5 && $density <= 2.5) {
            $this->score += 10;
        } else {
            $this->suggestions[] = [
                'type' => 'warning',
                'message' => "Keyword density is {$density}%. Aim for 0.5-2.5%"
            ];
        }
    }

    protected function checkContentLength() {
        $wordCount = str_word_count(strip_tags($this->content['body']));

        if ($wordCount >= 300) {
            $this->score += 10;
        } else {
            $this->suggestions[] = [
                'type' => 'error',
                'message' => "Content is only {$wordCount} words. Aim for at least 300 words"
            ];
        }
    }

    protected function checkReadability() {
        $text = strip_tags($this->content['body']);
        $score = $this->calculateFleschScore($text);

        if ($score >= 60) {
            $this->score += 10;
        } else {
            $this->suggestions[] = [
                'type' => 'warning',
                'message' => 'Content readability could be improved'
            ];
        }
    }

    private function calculateFleschScore($text) {
        // Flesch Reading Ease implementation
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = str_word_count($text);
        $syllables = $this->countSyllables($text);

        $score = 206.835 - 1.015 * ($words / count($sentences))
                         - 84.6 * ($syllables / $words);

        return max(0, min(100, $score));
    }

    private function countSyllables($text) {
        // Simplified syllable counter
        $words = str_word_count(strtolower($text), 1);
        $syllables = 0;

        foreach ($words as $word) {
            $syllables += preg_match_all('/[aeiouy]+/', $word);
        }

        return max(1, $syllables);
    }
}
```

```php
// app/Libraries/Theme/ThemeEngine.php
<?php
namespace App\Libraries\Theme;

class ThemeEngine {
    protected $activeTheme;
    protected $themePath;
    protected $config;

    public function __construct() {
        $this->activeTheme = $this->getActiveTheme();
        $this->themePath = FCPATH . 'themes/' . $this->activeTheme;
        $this->loadThemeConfig();
        $this->loadThemeFunctions();
    }

    public function render($template, $data = []) {
        $templateFile = $this->themePath . '/templates/' . $template . '.php';

        if (!file_exists($templateFile)) {
            throw new \RuntimeException("Template not found: {$template}");
        }

        extract($data);
        ob_start();
        include $templateFile;
        return ob_get_clean();
    }

    public function getHeader() {
        return $this->loadLayout('header');
    }

    public function getFooter() {
        return $this->loadLayout('footer');
    }

    public function getSidebar() {
        return $this->loadLayout('sidebar');
    }

    protected function loadLayout($layout) {
        $layoutFile = $this->themePath . '/layouts/' . $layout . '.php';

        if (file_exists($layoutFile)) {
            ob_start();
            include $layoutFile;
            return ob_get_clean();
        }

        return '';
    }

    protected function loadThemeConfig() {
        $configFile = $this->themePath . '/theme.json';

        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true);
        }
    }

    protected function loadThemeFunctions() {
        $functionsFile = $this->themePath . '/functions.php';

        if (file_exists($functionsFile)) {
            require_once $functionsFile;
        }
    }

    public function enqueueStyle($handle, $src) {
        $url = base_url('themes/' . $this->activeTheme . '/' . $src);
        echo '<link rel="stylesheet" href="' . $url . '">' . "\n";
    }

    public function enqueueScript($handle, $src) {
        $url = base_url('themes/' . $this->activeTheme . '/' . $src);
        echo '<script src="' . $url . '"></script>' . "\n";
    }

    protected function getActiveTheme() {
        $model = model('ThemeModel');
        $theme = $model->where('is_active', 1)->first();
        return $theme['slug'] ?? 'default';
    }
}
```

```php
// app/Libraries/Editor/BlockRenderer.php
<?php
namespace App\Libraries\Editor;

class BlockRenderer {
    protected $blocks = [];

    public function __construct() {
        $this->registerDefaultBlocks();
    }

    protected function registerDefaultBlocks() {
        $this->blocks['paragraph'] = function($attrs, $content) {
            return '<p>' . $content . '</p>';
        };

        $this->blocks['heading'] = function($attrs, $content) {
            $level = $attrs['level'] ?? 2;
            return '<h' . $level . '>' . $content . '</h' . $level . '>';
        };

        $this->blocks['image'] = function($attrs, $content) {
            $alt = $attrs['alt'] ?? '';
            $class = $attrs['align'] ?? '';
            return '<img src="' . $attrs['url'] . '" alt="' . $alt . '" class="' . $class . '" loading="lazy">';
        };

        $this->blocks['gallery'] = function($attrs, $content) {
            $html = '<div class="gallery">';
            foreach ($attrs['images'] as $image) {
                $html .= '<img src="' . $image['url'] . '" alt="' . $image['alt'] . '" loading="lazy">';
            }
            $html .= '</div>';
            return $html;
        };

        $this->blocks['video'] = function($attrs, $content) {
            if (isset($attrs['embed'])) {
                return '<div class="video-embed">' . $attrs['embed'] . '</div>';
            }
            return '<video src="' . $attrs['url'] . '" controls></video>';
        };

        $this->blocks['code'] = function($attrs, $content) {
            $language = $attrs['language'] ?? 'plaintext';
            return '<pre><code class="language-' . $language . '">' . htmlspecialchars($content) . '</code></pre>';
        };
    }

    public function render($blocksData) {
        $blocks = json_decode($blocksData, true);
        $html = '';

        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block);
        }

        return $html;
    }

    protected function renderBlock($block) {
        $type = $block['type'];
        $attrs = $block['attrs'] ?? [];
        $content = $block['content'] ?? '';

        if (isset($this->blocks[$type])) {
            return $this->blocks[$type]($attrs, $content);
        }

        return '<!-- Unknown block: ' . $type . ' -->';
    }

    public function registerBlock($type, callable $renderer) {
        $this->blocks[$type] = $renderer;
    }
}
```

---

## 7. ADMIN PANEL FEATURES

### 7.1 Dashboard

```php
- Site statistics overview
  - Total posts/pages
  - Total users
  - Comments pending moderation
  - Storage usage

- SEO Overview
  - Average SEO score
  - Top performing pages
  - SEO issues count

- Recent activity
  - Latest posts
  - Recent comments
  - User activity

- Quick actions
  - New post
  - New page
  - Media upload

- System health
  - PHP version
  - Database status
  - Cache status
  - Disk space
```

### 7.2 Admin Interface

```javascript
// Lightweight admin UI (no heavy frameworks)
- Responsive sidebar navigation
- Breadcrumb navigation
- Toast notifications
- Modal dialogs
- Data tables with search/filter
- Drag & drop interfaces
- Auto-save indicators
- Keyboard shortcuts
```

---

## 8. PERFORMANCE BENCHMARKS

### 8.1 Target Metrics

```
- RAM Usage: < 50MB (average)
- Page Load: < 1 second (frontend)
- Admin Load: < 1.5 seconds
- Database Queries: < 10 per page (with caching)
- Asset Size:
  - Admin CSS: < 100KB
  - Admin JS: < 150KB
  - Frontend CSS: < 50KB
  - Frontend JS: < 75KB
```

### 8.2 Optimization Techniques

```php
// Lazy Loading Models
class PostController extends BaseController {
    protected $postModel;

    protected function getPostModel() {
        if (!$this->postModel) {
            $this->postModel = model('PostModel');
        }
        return $this->postModel;
    }
}

// Query Optimization
$posts = $this->postModel
    ->select('id, title, slug, excerpt, created_at')
    ->where('status', 'published')
    ->orderBy('created_at', 'DESC')
    ->paginate(10);

// Eager Loading
$posts = $this->postModel
    ->with('author', 'categories', 'seo_meta')
    ->findAll();

// Cache Implementation
$cache = \Config\Services::cache();
$posts = $cache->remember('homepage_posts', 3600, function() {
    return $this->postModel->getPublished(10);
});
```

---

## 9. SECURITY FEATURES

```php
- CSRF Protection (built-in CI4)
- XSS Filtering
- SQL Injection Prevention (Query Builder)
- Password hashing (bcrypt)
- Role-based access control (RBAC)
- File upload validation
- Rate limiting (login attempts)
- Security headers
  - X-Frame-Options
  - X-Content-Type-Options
  - X-XSS-Protection
  - Content-Security-Policy
- Two-factor authentication
- Activity logging
- Automated security updates
```

---

## 10. INSTALLATION & SETUP

### 10.1 System Requirements

```
- PHP 8.1 or higher
- MySQL 8.0+ / MariaDB 10.6+
- Apache/Nginx with mod_rewrite
- PHP Extensions:
  - intl
  - mbstring
  - json
  - mysqlnd
  - gd or imagick
  - xml
  - curl
```

### 10.2 Installation Steps

```bash
# 1. Clone or download LightCMS
git clone https://github.com/lightcms/lightcms.git

# 2. Install dependencies
composer install

# 3. Set permissions
chmod -R 755 writable/
chmod -R 755 public/uploads/

# 4. Configure database
cp env .env
# Edit .env with your database credentials

# 5. Run migrations
php spark migrate

# 6. Run seeders
php spark db:seed InitialSeeder

# 7. Set up virtual host and access
http://yourdomain.com/admin/setup
```

### 10.3 One-Click Installer

```php
// Web-based installer interface
Step 1: System Requirements Check
Step 2: Database Configuration
Step 3: Site Information
  - Site Title
  - Admin Username
  - Admin Email
  - Admin Password
Step 4: Installation
Step 5: Complete (redirect to admin)
```

---

## 11. PLUGIN SYSTEM (Future Enhancement)

```php
// Plugin Architecture
/plugins/
  ├── contact-form/
  │   ├── Plugin.php
  │   ├── Controllers/
  │   ├── Models/
  │   ├── Views/
  │   └── plugin.json

// plugin.json
{
  "name": "Contact Form",
  "version": "1.0.0",
  "author": "LightCMS",
  "hooks": [
    "before_content",
    "after_content",
    "admin_menu"
  ]
}

// Hook System
Hooks::register('before_content', function($content) {
    return '<div class="notice">Important!</div>' . $content;
});
```

---

## 12. DEVELOPMENT ROADMAP

### Phase 1: Core Development (Month 1-2)
```
✓ Database schema design
✓ User authentication & authorization
✓ Basic post/page management
✓ Media library
✓ Theme system foundation
```

### Phase 2: Editor & SEO (Month 3-4)
```
✓ Block editor implementation
✓ SEO analyzer
✓ Meta management
✓ Sitemap generator
✓ Schema markup
```

### Phase 3: Advanced Features (Month 5-6)
```
✓ Theme customizer
✓ Widget system
✓ Menu builder
✓ Redirect manager
✓ Performance optimization
```

### Phase 4: Polish & Testing (Month 7-8)
```
✓ Admin UI refinement
✓ Security hardening
✓ Performance benchmarking
✓ Documentation
✓ Beta testing
```

---

## 13. DOCUMENTATION REQUIREMENTS

```
1. User Guide
   - Installation guide
   - Content creation
   - SEO optimization
   - Theme customization

2. Developer Guide
   - Theme development
   - Plugin development
   - Hooks & filters
   - API reference

3. API Documentation
   - REST API endpoints
   - Request/response examples
   - Authentication

4. Video Tutorials
   - Getting started
   - Creating your first post
   - SEO optimization
   - Theme customization
```

---

## 14. SUPPORT & MAINTENANCE

```
- GitHub Issues for bug tracking
- Community forum
- Email support (premium)
- Regular security updates
- Feature updates (quarterly)
- LTS version (2 years support)
```

---

## 15. MONETIZATION (Optional)

```
1. Free (Core)
   - All basic features
   - Community support

2. Premium Themes ($29-$99)
   - Professional designs
   - Premium support

3. Premium Plugins ($19-$49)
   - Advanced features
   - E-commerce integration

4. Pro Version ($99/year)
   - Priority support
   - Advanced SEO tools
   - White-label option
```

---

## 16. SUCCESS METRICS

```
- Page load time < 1 second
- RAM usage < 50MB
- SEO score accuracy > 90%
- User satisfaction > 4.5/5
- Theme compatibility > 95%
- Security vulnerabilities = 0 (critical)
```

---

## CONCLUSION

LightCMS dirancang dengan fokus utama pada:

1. **Performance**: Ultra-ringan dengan penggunaan RAM minimal
2. **SEO**: Fitur SEO comprehensive setara RankMath
3. **Flexibility**: Theme system yang powerful seperti WordPress
4. **Developer Experience**: Clean code, well-documented, easy to extend
5. **User Experience**: Intuitive admin interface, powerful editor

Dengan arsitektur yang solid dan fokus pada performance, LightCMS akan menjadi solusi CMS yang ideal untuk website yang membutuhkan kecepatan, SEO optimization, dan fleksibilitas theme tanpa mengorbankan resource server.
