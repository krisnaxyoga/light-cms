<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Editor\BlockParser;
use App\Models\ActivityLogModel;
use App\Models\CategoryModel;
use App\Models\NotificationModel;
use App\Models\PostModel;
use App\Models\SEOModel;
use App\Models\TagModel;
use App\Models\UserModel;
use Config\LightCMS as LightCMSConfig;
use Config\Services;

class PostController extends BaseController
{
    protected PostModel $postModel;

    public function __construct()
    {
        $this->postModel = new PostModel();
    }

    public function index(string $postType = 'post')
    {
        $status = $this->request->getGet('status');

        $query = $this->postModel
            ->select('posts.id, posts.title, posts.slug, posts.status, posts.author_id, posts.published_at, posts.updated_at, seo_meta.seo_score')
            ->join('seo_meta', 'seo_meta.post_id = posts.id', 'left')
            ->where('posts.post_type', $postType)
            ->orderBy('posts.updated_at', 'DESC');

        if ($status) {
            $query->where('posts.status', $status);
        }

        $posts = $query->paginate(20);

        return view('admin/posts/index', [
            'posts'    => $posts,
            'pager'    => $this->postModel->pager,
            'postType' => $postType,
        ]);
    }

    public function create(string $postType = 'post')
    {
        return view('admin/posts/form', [
            'post'       => null,
            'postType'   => $postType,
            'categories' => (new CategoryModel())->findAll(),
            'tags'       => (new TagModel())->findAll(),
        ]);
    }

    public function store()
    {
        $data = $this->collectPostInput();

        if (! $this->postModel->insert($data, false)) {
            session()->setFlashdata('errors', $this->postModel->errors());

            return redirect()->back()->withInput();
        }

        $postId = $this->postModel->getInsertID();
        $this->saveTaxonomies($postId);
        $this->saveSeoMeta($postId);

        (new ActivityLogModel())->record(session('userId'), 'create', 'post', $postId);
        session()->setFlashdata('success', 'Post created.');

        return redirect()->to('/admin/posts');
    }

    public function edit(int $id)
    {
        $post = $this->postModel->find($id);

        if ($post === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $post = $this->postModel->withRelations($post);

        return view('admin/posts/form', [
            'post'       => $post,
            'postType'   => $post['post_type'],
            'categories' => (new CategoryModel())->findAll(),
            'tags'       => (new TagModel())->findAll(),
        ]);
    }

    public function update(int $id)
    {
        $data = $this->collectPostInput($id);

        if (! $this->postModel->update($id, $data)) {
            session()->setFlashdata('errors', $this->postModel->errors());

            return redirect()->back()->withInput();
        }

        $this->saveTaxonomies($id);
        $this->saveSeoMeta($id);

        Services::queryCache()->invalidate('posts');
        Services::cacheManager()->flushTag('posts');

        (new ActivityLogModel())->record(session('userId'), 'update', 'post', $id);
        session()->setFlashdata('success', 'Post updated.');

        return redirect()->to('/admin/posts');
    }

    public function trash(int $id)
    {
        $this->postModel->update($id, ['status' => 'trash']);
        (new ActivityLogModel())->record(session('userId'), 'trash', 'post', $id);

        return redirect()->back();
    }

    public function restore(int $id)
    {
        $this->postModel->update($id, ['status' => 'draft']);

        return redirect()->back();
    }

    public function delete(int $id)
    {
        $this->postModel->delete($id);
        (new ActivityLogModel())->record(session('userId'), 'delete', 'post', $id);

        return redirect()->back();
    }

    /**
     * SEO score preview for the post editor (AJAX). Runs the analyzer
     * against the not-yet-saved draft content (PRD §3.1.A.3 real-time score).
     */
    public function analyze()
    {
        $blocksJson = (string) $this->request->getPost('content');
        $title      = (string) $this->request->getPost('title');
        $keyword    = (string) $this->request->getPost('focus_keyword');
        $metaDesc   = (string) $this->request->getPost('meta_description');

        $bodyHtml = Services::blockRenderer()->render($blocksJson);

        $result = Services::seoAnalyzer()->analyze([
            'title'            => $title,
            'body'             => $bodyHtml,
            'meta_description' => $metaDesc,
        ], $keyword);

        return $this->response->setJSON($result);
    }

    protected function collectPostInput(?int $id = null): array
    {
        $title = (string) $this->request->getPost('title');
        $slug  = (string) $this->request->getPost('slug') ?: url_title($title, '-', true);

        $parser     = new BlockParser();
        $blocks     = $parser->parse((string) $this->request->getPost('content'));
        $contentRaw = $parser->serialize($blocks);

        return [
            // Only present so the `is_unique[posts.slug,id,{id}]` rule can
            // resolve its {id} placeholder against *this* row on update;
            // doProtectFields() strips it again before the actual SQL
            // UPDATE since 'id' isn't in $allowedFields.
            'id'              => $id,
            'title'           => $title,
            'slug'            => $slug,
            'content'         => $contentRaw,
            'excerpt'         => (string) $this->request->getPost('excerpt') ?: lcms_excerpt($parser->toPlainText($blocks)),
            'author_id'       => (int) ($this->request->getPost('author_id') ?: session('userId')),
            'post_type'       => (string) $this->request->getPost('post_type') ?: 'post',
            'status'          => (string) $this->request->getPost('status') ?: 'draft',
            'featured_image'  => (string) $this->request->getPost('featured_image'),
            'comment_status'  => $this->request->getPost('comment_status') ? 'open' : 'closed',
            'published_at'    => (string) $this->request->getPost('status') === 'published'
                ? ($this->request->getPost('published_at') ?: date('Y-m-d H:i:s'))
                : $this->request->getPost('published_at'),
        ];
    }

    protected function saveTaxonomies(int $postId): void
    {
        $categoryIds = array_map('intval', (array) $this->request->getPost('categories'));
        $this->postModel->attachCategories($postId, $categoryIds);

        $tagNames = array_filter(array_map('trim', explode(',', (string) $this->request->getPost('tags'))));
        $tagModel = new TagModel();
        $tagIds   = array_map(static fn ($name) => $tagModel->findOrCreate($name), $tagNames);
        $this->postModel->attachTags($postId, $tagIds);
    }

    protected function saveSeoMeta(int $postId): void
    {
        $title        = (string) $this->request->getPost('title');
        $focusKeyword = (string) $this->request->getPost('focus_keyword');
        $ogImage      = (string) $this->request->getPost('og_image');

        $post     = $this->postModel->find($postId);
        $bodyHtml = Services::blockRenderer()->render($post['content'] ?? '[]');
        $author   = $post['author_id'] ? (new UserModel())->find($post['author_id']) : [];

        $autoSeo = Services::autoSeoGenerator();

        // "Zero configuration" fallbacks (PRD ADDENDUM §2) — a manual
        // value the author actually typed always wins.
        $metaDescription = (string) $this->request->getPost('meta_description')
            ?: $autoSeo->generateMetaDescription($bodyHtml);

        $schemaData = $autoSeo->buildSchema(
            $post,
            $author ?: [],
            $ogImage ?: ($post['featured_image'] ?? null),
            $bodyHtml
        );

        $analysis = Services::seoAnalyzer()->analyze([
            'title'            => $title,
            'body'             => $bodyHtml,
            'meta_description' => $metaDescription,
        ], $focusKeyword);

        (new SEOModel())->saveForPost($postId, [
            'meta_title'       => (string) $this->request->getPost('meta_title'),
            'meta_description' => $metaDescription,
            'focus_keyword'    => $focusKeyword,
            'canonical_url'    => (string) $this->request->getPost('canonical_url'),
            'robots_index'     => $this->request->getPost('robots_index') ? 1 : 0,
            'robots_follow'    => $this->request->getPost('robots_follow') ? 1 : 0,
            'og_image'         => $ogImage,
            'schema_data'      => $schemaData,
            'seo_score'        => $analysis['score'],
        ]);

        $this->notifyIfLowSeoScore($postId, $title, $analysis['score']);
    }

    /**
     * One-time-per-day "low SEO score" dashboard notice (PRD ADDENDUM
     * §13 notifyLowSEO) — only for content actually going live; a low
     * score on a draft still being written isn't worth interrupting for.
     */
    protected function notifyIfLowSeoScore(int $postId, string $title, int $score): void
    {
        $threshold = config(LightCMSConfig::class)->lowSeoScoreThreshold;

        if ($score >= $threshold || (string) $this->request->getPost('status') !== 'published') {
            return;
        }

        $notifications = new NotificationModel();
        $dedupeKey     = "Low SEO score: {$title}";

        if ($notifications->existsRecently($dedupeKey, 1440)) {
            return;
        }

        $notifications->insert([
            'type'        => 'warning',
            'title'       => $dedupeKey,
            'message'     => "\"{$title}\" scored {$score}/100. Add a focus keyword, internal links, or more content to improve it.",
            'action_url'  => site_url("admin/posts/{$postId}/edit"),
            'action_text' => 'Improve SEO',
        ]);
    }
}
