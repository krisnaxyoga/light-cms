<?php

namespace Tests\Unit;

use App\Libraries\Editor\BlockRenderer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Rendering contract for the block editor's output — in particular the
 * image block's link support (PRD §3.2 image + backlink), where the
 * escaping and URL-scheme rules actually matter.
 */
final class BlockRendererTest extends CIUnitTestCase
{
    private BlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new BlockRenderer();
    }

    private function renderImage(array $attrs): string
    {
        return $this->renderer->render(json_encode([['type' => 'image', 'attrs' => $attrs, 'content' => '']]));
    }

    public function testPlainImageIsUnwrapped(): void
    {
        $html = $this->renderImage(['url' => '/uploads/a.jpg', 'alt' => 'A cat']);

        $this->assertStringContainsString('<img src="/uploads/a.jpg" alt="A cat"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringNotContainsString('<a ', $html);
    }

    public function testImageLinkWrapsTheImage(): void
    {
        $html = $this->renderImage(['url' => '/uploads/a.jpg', 'alt' => 'A cat', 'href' => 'https://example.com/target']);

        $this->assertStringContainsString('<a href="https://example.com/target"><img ', $html);
        $this->assertStringContainsString('</a>', $html);
        $this->assertStringNotContainsString('target=', $html);
        $this->assertStringNotContainsString('rel=', $html);
    }

    public function testNewTabAlwaysGetsNoopenerNoreferrer(): void
    {
        $html = $this->renderImage(['url' => '/a.jpg', 'href' => 'https://example.com', 'link_target' => '_blank']);

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('noopener', $html);
        $this->assertStringContainsString('noreferrer', $html);
    }

    public function testAuthorRelIsKeptAndDeduplicated(): void
    {
        $html = $this->renderImage([
            'url'         => '/a.jpg',
            'href'        => 'https://example.com',
            'link_target' => '_blank',
            'rel'         => 'nofollow noopener',
        ]);

        preg_match('/rel="([^"]+)"/', $html, $m);
        $tokens = explode(' ', $m[1] ?? '');

        $this->assertSame(['nofollow', 'noopener', 'noreferrer'], $tokens);
    }

    /**
     * The editor also filters this, but the renderer must not trust that —
     * block JSON can reach the DB through the API or an import.
     *
     * @dataProvider dangerousUrls
     */
    public function testDangerousHrefsAreDropped(string $href): void
    {
        $html = $this->renderImage(['url' => '/a.jpg', 'href' => $href]);

        $this->assertStringNotContainsString('<a ', $html, "Should not link: {$href}");
    }

    public static function dangerousUrls(): array
    {
        return [
            'javascript'         => ['javascript:alert(1)'],
            'javascript spaced'  => ['  javascript:alert(1)'],
            'data uri'           => ['data:text/html;base64,PHNjcmlwdD4='],
            'vbscript'           => ['vbscript:msgbox(1)'],
            'bare domain'        => ['example.com'],
        ];
    }

    /** @dataProvider safeUrls */
    public function testSafeSchemesAreAllowed(string $href): void
    {
        $html = $this->renderImage(['url' => '/a.jpg', 'href' => $href]);

        $this->assertStringContainsString('<a href=', $html, "Should link: {$href}");
    }

    public static function safeUrls(): array
    {
        return [
            'https'    => ['https://example.com/x'],
            'http'     => ['http://example.com/x'],
            'relative' => ['/about'],
            'anchor'   => ['#section'],
            'mailto'   => ['mailto:hi@example.com'],
            'tel'      => ['tel:+62123'],
        ];
    }

    public function testHrefIsEscaped(): void
    {
        $html = $this->renderImage(['url' => '/a.jpg', 'href' => 'https://example.com/?a=1&b="x"']);

        $this->assertStringNotContainsString('&b="x"', $html);
        $this->assertStringContainsString('&amp;', $html);
    }

    public function testAlignmentMovesToTheOutermostElement(): void
    {
        $plain   = $this->renderImage(['url' => '/a.jpg', 'align' => 'alignleft']);
        $linked  = $this->renderImage(['url' => '/a.jpg', 'align' => 'alignleft', 'href' => '/x']);
        $caption = $this->renderImage(['url' => '/a.jpg', 'align' => 'alignleft', 'href' => '/x', 'caption' => 'Hi']);

        $this->assertStringContainsString('<img src="/a.jpg" alt="" class="alignleft"', $plain);
        $this->assertStringContainsString('<a href="/x" class="alignleft"><img ', $linked);
        $this->assertStringContainsString('<figure class="alignleft">', $caption);
        // …and not duplicated onto the inner <img>, which would double-float it.
        $this->assertSame(1, substr_count($caption, 'alignleft'));
    }

    public function testCaptionIsEscapedAndKeepsTheLinkInside(): void
    {
        $html = $this->renderImage(['url' => '/a.jpg', 'href' => '/x', 'caption' => 'Cat & <dog>']);

        $this->assertStringContainsString('<figcaption>Cat &amp; &lt;dog&gt;</figcaption>', $html);
        $this->assertMatchesRegularExpression('#<figure><a href="/x"><img [^>]*></a><figcaption>#', $html);
    }
}
