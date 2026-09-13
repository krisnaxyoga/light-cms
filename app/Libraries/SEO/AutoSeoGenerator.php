<?php

namespace App\Libraries\SEO;

use Config\LightCMS as LightCMSConfig;

/**
 * "Zero configuration" SEO fill-ins (PRD ADDENDUM §2): auto meta
 * description and auto schema (Article + detected FAQ), used only when
 * the author left the corresponding field blank — a manual value always
 * wins. Nothing here calls out to the network; it all works off the
 * post's own rendered content.
 */
class AutoSeoGenerator
{
    public function __construct(protected SchemaGenerator $schemaGenerator = new SchemaGenerator())
    {
    }

    /**
     * First few sentences of the rendered body, cut to length without
     * splitting mid-sentence where possible (PRD ADDENDUM §2.2).
     */
    public function generateMetaDescription(string $bodyHtml, ?int $maxLength = null): string
    {
        $maxLength = $maxLength ?? config(LightCMSConfig::class)->autoMetaDescriptionLength;
        $text      = trim(preg_replace('/\s+/', ' ', strip_tags($bodyHtml)) ?? '');

        if ($text === '') {
            return '';
        }

        $sentences   = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $description = '';

        foreach ($sentences as $sentence) {
            $candidate = $description === '' ? $sentence : "{$description} {$sentence}";

            if (mb_strlen($candidate) > $maxLength) {
                break;
            }

            $description = $candidate;
        }

        // No single sentence fit (one very long run-on sentence) — hard cut.
        if ($description === '') {
            $description = mb_substr($text, 0, $maxLength - 1) . '…';
        }

        return $description;
    }

    /**
     * Detects "heading immediately followed by a paragraph" pairs in the
     * rendered body as FAQ candidates (PRD ADDENDUM §2.3
     * autoDetectFAQSchema). Deliberately simple pattern matching — a false
     * negative here just means no FAQ schema, which is safe.
     *
     * Two guards keep this from over-matching ordinary prose:
     *  - `</h\1>` backreferences the opening level, so an <h2> can't be
     *    "closed" by the next <h3> down the page (they used to match as
     *    one pair, concatenating both headings' text into a single bogus
     *    "question").
     *  - the heading must actually contain a "?" — a real FAQ is phrased
     *    as a question; a normal section heading immediately followed by
     *    its own explanatory paragraph (extremely common outside of FAQ
     *    content) isn't one, and neither is a CTA block's title (it
     *    renders as an <h3> too). A false negative here just means no FAQ
     *    schema, which is safe; a false positive means mislabeling
     *    ordinary content as a Google-eligible FAQ rich result, which isn't.
     *
     * @return array<int, array{question: string, answer: string}>
     */
    public function detectFaqs(string $bodyHtml): array
    {
        preg_match_all(
            '#<h([2-4])[^>]*>(.*?)</h\1>\s*<p>(.*?)</p>#is',
            $bodyHtml,
            $matches,
            PREG_SET_ORDER
        );

        $faqs = [];

        foreach ($matches as $match) {
            $question = trim(strip_tags($match[2]));
            $answer   = trim(strip_tags($match[3]));

            if ($question !== '' && $answer !== '' && str_contains($question, '?')) {
                $faqs[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $faqs;
    }

    /**
     * Builds the schema to store in seo_meta.schema_data: always an
     * Article, plus an FAQPage merged in via @graph when enough Q/A pairs
     * were detected (PRD ADDENDUM §2.3/§2.4).
     */
    public function buildSchema(array $post, array $author, ?string $image, string $bodyHtml): array
    {
        $graph = [$this->schemaGenerator->article($post, $author, $image)];

        $faqs = $this->detectFaqs($bodyHtml);

        if (count($faqs) >= config(LightCMSConfig::class)->autoFaqMinPairs) {
            $graph[] = $this->schemaGenerator->faq($faqs);
        }

        if (count($graph) === 1) {
            return $graph[0];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph'   => array_map(
                static fn (array $schema) => array_diff_key($schema, ['@context' => true]),
                $graph
            ),
        ];
    }
}
