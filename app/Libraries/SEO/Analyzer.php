<?php

namespace App\Libraries\SEO;

/**
 * On-page SEO analyzer (PRD §3.1.A.2-3 / §6.2 sample, hardened for real use).
 *
 * Expects $content as:
 *   [
 *     'title'             => string,
 *     'body'              => string (rendered HTML),
 *     'meta_description'  => string|null,
 *   ]
 *
 * Score is 0-100. Each check awards points independently, so a missing
 * focus keyword never causes a divide-by-zero — it just scores 0 for
 * that check.
 */
class Analyzer
{
    protected array $content = [];
    protected string $focusKeyword = '';
    protected int $score = 0;
    protected array $suggestions = [];

    public function analyze(array $content, string $focusKeyword): array
    {
        $this->content      = $content + ['title' => '', 'body' => '', 'meta_description' => ''];
        $this->focusKeyword = trim($focusKeyword);
        $this->score        = 0;
        $this->suggestions  = [];

        $this->checkKeywordInTitle();
        $this->checkKeywordInMetaDescription();
        $this->checkKeywordDensity();
        $this->checkContentLength();
        $this->checkHeadingStructure();
        $this->checkImageAltTags();
        $this->checkInternalLinks();
        $this->checkReadability();

        return [
            'score'       => min(100, $this->score),
            'rating'      => $this->rating(min(100, $this->score)),
            'suggestions' => $this->suggestions,
        ];
    }

    public function rating(int $score): string
    {
        $thresholds = config(\Config\LightCMS::class)->seoScoreThresholds;

        if ($score >= $thresholds['good']) {
            return 'green';
        }

        return $score >= $thresholds['ok'] ? 'yellow' : 'red';
    }

    protected function addSuggestion(string $type, string $message): void
    {
        $this->suggestions[] = ['type' => $type, 'message' => $message];
    }

    protected function plainBody(): string
    {
        return trim(strip_tags($this->content['body']));
    }

    protected function checkKeywordInTitle(): void
    {
        if ($this->focusKeyword === '') {
            $this->addSuggestion('error', 'Set a focus keyword to enable keyword-based checks.');

            return;
        }

        if (stripos($this->content['title'], $this->focusKeyword) !== false) {
            $this->score += 15;
        } else {
            $this->addSuggestion('error', 'Focus keyword not found in the title.');
        }
    }

    protected function checkKeywordInMetaDescription(): void
    {
        if ($this->focusKeyword === '') {
            return;
        }

        if (stripos((string) $this->content['meta_description'], $this->focusKeyword) !== false) {
            $this->score += 10;
        } else {
            $this->addSuggestion('warning', 'Focus keyword not found in the meta description.');
        }
    }

    protected function checkKeywordDensity(): void
    {
        if ($this->focusKeyword === '') {
            return;
        }

        $body      = $this->plainBody();
        $wordCount = str_word_count($body);

        if ($wordCount === 0) {
            $this->addSuggestion('error', 'Content is empty — cannot compute keyword density.');

            return;
        }

        $keywordCount = substr_count(strtolower($body), strtolower($this->focusKeyword));
        $density      = ($keywordCount / $wordCount) * 100;

        if ($density >= 0.5 && $density <= 2.5) {
            $this->score += 10;
        } else {
            $this->addSuggestion('warning', sprintf('Keyword density is %.2f%%. Aim for 0.5-2.5%%.', $density));
        }
    }

    protected function checkContentLength(): void
    {
        $wordCount = str_word_count($this->plainBody());

        if ($wordCount >= 300) {
            $this->score += 10;
        } else {
            $this->addSuggestion('error', "Content is only {$wordCount} words. Aim for at least 300 words.");
        }
    }

    protected function checkHeadingStructure(): void
    {
        preg_match_all('/<h([1-6])[^>]*>/i', $this->content['body'], $matches);
        $levels = $matches[1] ?? [];

        if ($levels === []) {
            $this->addSuggestion('warning', 'No subheadings (H2-H6) found. Break up content with headings.');

            return;
        }

        $h1Count = count(array_filter($levels, static fn ($level) => (int) $level === 1));

        if ($h1Count > 0) {
            $this->addSuggestion('warning', 'Content body contains an H1 — the post title already renders as H1. Use H2+ instead.');

            return;
        }

        $this->score += 15;
    }

    protected function checkImageAltTags(): void
    {
        preg_match_all('/<img\b[^>]*>/i', $this->content['body'], $matches);
        $images = $matches[0] ?? [];

        if ($images === []) {
            // No images to check — treat as neutral, not a failure.
            $this->score += 15;

            return;
        }

        $missing = 0;
        foreach ($images as $img) {
            if (! preg_match('/alt\s*=\s*"[^"]+"/i', $img)) {
                $missing++;
            }
        }

        if ($missing === 0) {
            $this->score += 15;
        } else {
            $this->addSuggestion('error', "{$missing} image(s) are missing descriptive alt text.");
        }
    }

    protected function checkInternalLinks(): void
    {
        preg_match_all('/<a\s[^>]*href\s*=\s*"([^"]+)"/i', $this->content['body'], $matches);
        $hrefs = $matches[1] ?? [];

        $internal = 0;
        $external = 0;
        $baseHost = parse_url(base_url(), PHP_URL_HOST);

        foreach ($hrefs as $href) {
            if (str_starts_with($href, '/') || (parse_url($href, PHP_URL_HOST) === $baseHost)) {
                $internal++;
            } else {
                $external++;
            }
        }

        if ($internal > 0) {
            $this->score += 15;
        } else {
            $this->addSuggestion('warning', 'No internal links found. Link to related content on your own site.');
        }
    }

    protected function checkReadability(): void
    {
        $text  = $this->plainBody();
        $score = $this->calculateFleschScore($text);

        if ($score === null) {
            return;
        }

        if ($score >= 60) {
            $this->score += 10;
        } else {
            $this->addSuggestion('warning', sprintf('Readability score is %.0f (Flesch). Aim for 60+ by shortening sentences and words.', $score));
        }
    }

    protected function calculateFleschScore(string $text): ?float
    {
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words     = str_word_count($text);

        if ($words === 0 || count($sentences) === 0) {
            return null;
        }

        $syllables = $this->countSyllables($text);

        $score = 206.835 - 1.015 * ($words / count($sentences)) - 84.6 * ($syllables / $words);

        return max(0.0, min(100.0, $score));
    }

    protected function countSyllables(string $text): int
    {
        $words     = str_word_count(strtolower($text), 1);
        $syllables = 0;

        foreach ($words as $word) {
            $syllables += max(1, preg_match_all('/[aeiouy]+/', $word));
        }

        return max(1, $syllables);
    }
}
