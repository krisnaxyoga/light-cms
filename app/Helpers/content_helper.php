<?php

if (! function_exists('lcms_excerpt')) {
    /**
     * Word-based excerpt (PRD §3.6.B "Excerpt length" setting).
     */
    function lcms_excerpt(string $html, int $wordLimit = 55, string $more = '&hellip;'): string
    {
        $text  = trim(strip_tags($html));
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (count($words) <= $wordLimit) {
            return $text;
        }

        return implode(' ', array_slice($words, 0, $wordLimit)) . ' ' . $more;
    }
}

if (! function_exists('lcms_word_count')) {
    function lcms_word_count(string $html): int
    {
        return str_word_count(trim(strip_tags($html)));
    }
}

if (! function_exists('lcms_reading_time')) {
    /**
     * Reading-time estimate in whole minutes (PRD §3.2.C "Reading time
     * estimator"), based on an average adult silent-reading speed.
     */
    function lcms_reading_time(string $html, int $wordsPerMinute = 200): int
    {
        $words = lcms_word_count($html);

        return max(1, (int) ceil($words / $wordsPerMinute));
    }
}

if (! function_exists('lcms_time_ago')) {
    function lcms_time_ago(string $datetime): string
    {
        $timestamp = strtotime($datetime);

        if (! $timestamp) {
            return $datetime;
        }

        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'just now';
        }

        $units = [
            31536000 => 'year',
            2592000  => 'month',
            86400    => 'day',
            3600     => 'hour',
            60       => 'minute',
        ];

        foreach ($units as $seconds => $label) {
            $count = intdiv($diff, $seconds);

            if ($count >= 1) {
                return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
            }
        }

        return 'just now';
    }
}

if (! function_exists('status_badge')) {
    /**
     * Post/page status as a daisyUI badge — the admin lists all render it
     * the same way, so the colour mapping lives in one place.
     */
    function status_badge(string $status): string
    {
        $class = match ($status) {
            'published' => 'badge-success',
            'scheduled' => 'badge-info',
            'trash'     => 'badge-error',
            default     => 'badge-ghost',
        };

        return '<span class="badge badge-sm ' . $class . '">' . esc($status) . '</span>';
    }
}
