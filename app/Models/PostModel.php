<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Services;

class PostModel extends Model
{
    protected $table         = 'posts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'title', 'slug', 'locale', 'translation_group_id', 'content', 'excerpt', 'author_id',
        'post_type', 'status', 'featured_image', 'comment_status', 'view_count', 'published_at',
    ];

    protected $validationRules = [
        'title'  => 'required|max_length[255]',
        // Slugs are unique per language (Admin -> Languages), not site-wide,
        // so /about-us and /id/about-us can both exist. The custom rule
        // (App\Validation\LocaleRules::slug_unique_in_locale) reads `locale`
        // and `id` from the data array, which is why both are passed in
        // alongside the row.
        'slug'   => 'required|max_length[255]|slug_unique_in_locale[posts]',
        'locale' => 'permit_empty|max_length[10]',
        // 'id' is only ever present so the rules above can exclude the row
        // being edited; doProtectFields() strips it before the actual SQL.
        'id'     => 'permit_empty|is_natural_no_zero',
    ];

    // -- Automation hooks (PRD ADDENDUM §3.1/§8.1) ------------------------
    // Capture the pre-update slug/status so afterUpdate can tell whether
    // they actually changed; instance state is only compared within one
    // update() call, and a fresh Model is constructed per request anyway.
    protected $beforeUpdate = ['captureOldRowState'];
    protected $afterInsert  = ['assignTranslationGroup', 'regenerateSitemapIfRelevant'];
    protected $afterUpdate  = ['createRedirectForSlugChange', 'regenerateSitemapIfRelevant'];
    protected $afterDelete  = ['regenerateSitemapAfterDelete'];

    protected ?string $pendingOldSlug   = null;
    protected ?string $pendingOldStatus = null;
    protected ?string $pendingOldLocale = null;

    /**
     * Published posts, newest first — the query shape used by the
     * homepage/archive (PRD §8.2: select only what's needed, cache the rest).
     */
    public function getPublished(int $limit = 10, int $offset = 0, string $postType = 'post', ?string $locale = null): array
    {
        $query = $this->select('id, title, slug, locale, post_type, excerpt, featured_image, author_id, published_at')
            ->where('status', 'published')
            ->where('post_type', $postType)
            ->where('published_at <=', date('Y-m-d H:i:s'))
            ->orderBy('published_at', 'DESC');

        if ($locale !== null) {
            $query->where('locale', $locale);
        }

        return $query->findAll($limit, $offset);
    }

    /**
     * @param string|null $locale restrict to one language; null keeps the
     *                            single-language behaviour (any locale)
     */
    public function findBySlug(string $slug, ?string $locale = null): ?array
    {
        $query = $this->where('slug', $slug);

        if ($locale !== null) {
            $query->where('locale', $locale);
        }

        return $query->first();
    }

    /**
     * Every language version of one piece of content (the row itself
     * included), keyed by locale.
     *
     * @return array<string, array>
     */
    public function translations(?int $translationGroupId, bool $publishedOnly = true): array
    {
        if (! $translationGroupId) {
            return [];
        }

        $query = $this->select('id, title, slug, locale, status, post_type')
            ->where('translation_group_id', $translationGroupId);

        if ($publishedOnly) {
            $query->where('status', 'published');
        }

        $byLocale = [];

        foreach ($query->findAll() as $row) {
            $byLocale[$row['locale']] = $row;
        }

        return $byLocale;
    }

    /**
     * Attach categories, tags and SEO meta to a single post row.
     * CI4's query builder has no eager-loading sugar, so this issues a
     * small, explicit set of follow-up queries instead of N+1-ing blindly.
     */
    public function withRelations(array $post): array
    {
        $db = $this->db;

        $post['categories'] = $db->table('categories c')
            ->select('c.id, c.name, c.slug')
            ->join('post_categories pc', 'pc.category_id = c.id')
            ->where('pc.post_id', $post['id'])
            ->get()->getResultArray();

        $post['tags'] = $db->table('tags t')
            ->select('t.id, t.name, t.slug')
            ->join('post_tags pt', 'pt.tag_id = t.id')
            ->where('pt.post_id', $post['id'])
            ->get()->getResultArray();

        $post['seo_meta'] = $db->table('seo_meta')
            ->where('post_id', $post['id'])
            ->get()->getRowArray();

        return $post;
    }

    public function incrementViewCount(int $id): bool
    {
        return $this->set('view_count', 'view_count + 1', false)->where('id', $id)->update();
    }

    public function attachCategories(int $postId, array $categoryIds): void
    {
        $this->db->table('post_categories')->where('post_id', $postId)->delete();

        $rows = array_map(static fn ($categoryId) => [
            'post_id'     => $postId,
            'category_id' => $categoryId,
        ], $categoryIds);

        if ($rows !== []) {
            $this->db->table('post_categories')->insertBatch($rows);
        }
    }

    public function attachTags(int $postId, array $tagIds): void
    {
        $this->db->table('post_tags')->where('post_id', $postId)->delete();

        $rows = array_map(static fn ($tagId) => [
            'post_id' => $postId,
            'tag_id'  => $tagId,
        ], $tagIds);

        if ($rows !== []) {
            $this->db->table('post_tags')->insertBatch($rows);
        }
    }

    /**
     * A post created without a translation group is the original of a new
     * group: the group id is simply its own id. Translations are inserted
     * with the source's group id, so this is a no-op for them.
     */
    protected function assignTranslationGroup(array $eventData): array
    {
        $id = $eventData['id'] ?? null;

        if ($id && empty($eventData['data']['translation_group_id'])) {
            $this->db->table($this->table)->where('id', $id)->update(['translation_group_id' => $id]);
        }

        return $eventData;
    }

    /**
     * Stash the slug/status this row currently has in the DB —
     * deliberately a raw query builder call on $this->db rather than
     * $this->find(), so it can't disturb the WHERE clause update() is
     * about to build on this model's own (shared, cached) query builder.
     */
    protected function captureOldRowState(array $eventData): array
    {
        $this->pendingOldSlug   = null;
        $this->pendingOldStatus = null;
        $this->pendingOldLocale = null;

        $id = is_array($eventData['id']) ? ($eventData['id'][0] ?? null) : $eventData['id'];

        if ($id !== null) {
            $existing = $this->db->table($this->table)->select('slug, status, locale')->where('id', $id)->get()->getRowArray();
            $this->pendingOldSlug   = $existing['slug'] ?? null;
            $this->pendingOldStatus = $existing['status'] ?? null;
            $this->pendingOldLocale = $existing['locale'] ?? null;
        }

        return $eventData;
    }

    /**
     * Auto-redirect old slug -> new slug (PRD ADDENDUM §8.1 onSlugChange)
     * so links to a renamed post don't silently 404. Paths carry the
     * language prefix, so a rename in one language never redirects
     * another language's URL.
     */
    protected function createRedirectForSlugChange(array $eventData): array
    {
        $newSlug = $eventData['data']['slug'] ?? null;
        $oldSlug = $this->pendingOldSlug;

        if (! $oldSlug || ! $newSlug || $oldSlug === $newSlug) {
            return $eventData;
        }

        // post_type doesn't change once a post/page is created (the admin
        // form fixes it per create/edit context), so the same value is
        // valid for both the old and new path's /blog/ prefix decision.
        $postType  = $eventData['data']['post_type'] ?? 'post';
        $newLocale = $eventData['data']['locale'] ?? $this->pendingOldLocale;
        $oldPath   = '/' . post_path(['slug' => $oldSlug, 'post_type' => $postType], $this->pendingOldLocale);
        $newPath   = '/' . post_path(['slug' => $newSlug, 'post_type' => $postType], $newLocale);

        $redirects = new RedirectModel();

        if (! $redirects->where('source_url', $oldPath)->first()) {
            $redirects->insert([
                'source_url'    => $oldPath,
                'target_url'    => $newPath,
                'redirect_type' => '301',
                'status'        => 1,
            ]);
        }

        return $eventData;
    }

    /**
     * Auto-regenerate sitemap.xml whenever the write could change what
     * belongs in it: the new status is 'published' (add/update lastmod),
     * or the row *was* published before this write (unpublish/trash should
     * drop it) — PRD ADDENDUM §3.1 SitemapAutomation::afterPostSave. Runs
     * synchronously; fine for a scaffold's post volume, but a high-traffic
     * site should move this to a queued job instead of blocking the save.
     */
    protected function regenerateSitemapIfRelevant(array $eventData): array
    {
        $newStatus = $eventData['data']['status'] ?? null;
        $oldStatus = $this->pendingOldStatus;
        $this->pendingOldStatus = null;

        if ($newStatus === 'published' || $oldStatus === 'published') {
            Services::sitemapGenerator()->writeFiles();
        }

        return $eventData;
    }

    protected function regenerateSitemapAfterDelete(array $eventData): array
    {
        Services::sitemapGenerator()->writeFiles();

        return $eventData;
    }
}
