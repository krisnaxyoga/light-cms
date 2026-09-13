<?php

/**
 * Single source of truth for the site's WhatsApp contact number
 * (Admin -> Settings -> Contact). Before this helper existed, the number
 * was hand-typed as a literal string in at least 8 places across two
 * unrelated config systems (theme.json's `contact_phone` and
 * App\Libraries\Homepage\HomepageContent's defaults) — changing the
 * number meant editing every one of them. Every WhatsApp link/label in
 * the project should go through these three functions instead of
 * building "https://wa.me/..." or typing the number itself.
 */

if (! function_exists('whatsapp_number')) {
    /**
     * Human-readable form, e.g. "+62 822 8263 8682" — for display text
     * ("WhatsApp: ..."), not for building a wa.me URL (see whatsapp_digits()).
     */
    function whatsapp_number(): string
    {
        return (string) site_setting('whatsapp_number', '+62 822 8263 8682');
    }
}

if (! function_exists('whatsapp_digits')) {
    /** whatsapp_number() stripped to the digits a wa.me URL expects. */
    function whatsapp_digits(): string
    {
        return (string) preg_replace('/\D/', '', whatsapp_number());
    }
}

if (! function_exists('whatsapp_url')) {
    /**
     * A wa.me link to the site's WhatsApp number, optionally pre-filling
     * the chat with $message (used as-is, not merged with any existing
     * query string — call it with the full message you want, not a
     * pre-built URL).
     */
    function whatsapp_url(string $message = ''): string
    {
        $url = 'https://wa.me/' . whatsapp_digits();

        return $message === '' ? $url : $url . '?text=' . rawurlencode($message);
    }
}
