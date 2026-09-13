<?php

namespace App\Libraries\SEO;

/**
 * JSON-LD structured data builder (PRD §3.1.A.4 / §3.1.B.3).
 * Each method returns a plain array ready for json_encode(); call
 * ->toScriptTag() to get the finished <script type="application/ld+json">.
 */
class SchemaGenerator
{
    public function article(array $post, array $author = [], ?string $image = null): array
    {
        $locale = \Config\Services::locale();
        $url    = post_url($post, $post['locale'] ?? null);

        return [
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => $post['title'] ?? '',
            'description'   => $post['excerpt'] ?? '',
            'image'         => $image ? [$image] : [],
            'inLanguage'    => $post['locale'] ?? $locale->current(),
            'datePublished' => $this->toIso($post['published_at'] ?? null),
            'dateModified'  => $this->toIso($post['updated_at'] ?? $post['published_at'] ?? null),
            'author'        => [
                '@type' => 'Person',
                'name'  => $author['display_name'] ?? $author['username'] ?? 'Unknown',
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => $url,
            ],
        ];
    }

    /**
     * @param array<int, array{name: string, url: string}> $trail
     */
    public function breadcrumb(array $trail): array
    {
        $items = [];

        foreach ($trail as $position => $crumb) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position + 1,
                'name'     => $crumb['name'],
                'item'     => $crumb['url'],
            ];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * @param array<int, array{question: string, answer: string}> $faqs
     */
    public function faq(array $faqs): array
    {
        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(static fn ($faq) => [
                '@type'          => 'Question',
                'name'           => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $faq['answer'],
                ],
            ], $faqs),
        ];
    }

    public function product(array $product): array
    {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $product['name'] ?? '',
            'description' => $product['description'] ?? '',
            'image'       => $product['image'] ?? '',
            'sku'         => $product['sku'] ?? '',
            'offers'      => [
                '@type'         => 'Offer',
                'price'         => $product['price'] ?? '0.00',
                'priceCurrency' => $product['currency'] ?? 'IDR',
                'availability'  => $product['in_stock'] ?? true
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ],
        ];
    }

    public function localBusiness(array $business): array
    {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'LocalBusiness',
            'name'        => $business['name'] ?? '',
            'image'       => $business['image'] ?? '',
            'telephone'   => $business['phone'] ?? '',
            'address'     => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $business['street'] ?? '',
                'addressLocality' => $business['city'] ?? '',
                'addressRegion'   => $business['region'] ?? '',
                'postalCode'      => $business['postal_code'] ?? '',
                'addressCountry'  => $business['country'] ?? 'ID',
            ],
        ];
    }

    public function toScriptTag(array $schema): string
    {
        return '<script type="application/ld+json">'
            . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . '</script>';
    }

    protected function toIso(?string $datetime): ?string
    {
        if (! $datetime) {
            return null;
        }

        $timestamp = strtotime($datetime);

        return $timestamp ? date(DATE_ATOM, $timestamp) : null;
    }
}
