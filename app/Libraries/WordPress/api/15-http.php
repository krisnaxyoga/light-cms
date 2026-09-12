<?php

/**
 * Outbound HTTP (wp_remote_*) on CodeIgniter's CURLRequest and email on
 * its Email service. Both honour Config\WordPress: remote requests can be
 * disabled outright, and every call gets a timeout so third-party code
 * cannot hang a page load indefinitely.
 */

if (! function_exists('wp_remote_request')) {
    function wp_remote_request(string $url, array $args = [])
    {
        $config = config(Config\WordPress::class);

        if (! $config->allowRemoteRequests) {
            return new WP_Error('http_request_blocked', 'Outbound HTTP is disabled in Config\\WordPress.');
        }

        $args = wp_parse_args($args, [
            'method'      => 'GET',
            'timeout'     => $config->remoteTimeout,
            'redirection' => 5,
            'headers'     => [],
            'body'        => null,
            'sslverify'   => true,
        ]);

        try {
            $client = Config\Services::curlrequest([
                'timeout'         => (float) $args['timeout'],
                'verify'          => (bool) $args['sslverify'],
                'http_errors'     => false,
                'allow_redirects' => ['max' => (int) $args['redirection']],
            ], null, null, false);

            $options = ['headers' => (array) $args['headers']];

            if ($args['body'] !== null && $args['body'] !== '') {
                $options['body'] = is_array($args['body']) ? http_build_query($args['body']) : $args['body'];
            }

            $response = $client->request(strtoupper((string) $args['method']), $url, $options);
        } catch (\Throwable $e) {
            return new WP_Error('http_request_failed', $e->getMessage());
        }

        $headers = [];

        foreach ($response->headers() as $name => $header) {
            $headers[strtolower((string) $name)] = $response->getHeaderLine((string) $name);
        }

        return [
            'headers'  => $headers,
            'body'     => $response->getBody(),
            'response' => ['code' => $response->getStatusCode(), 'message' => $response->getReasonPhrase()],
            'cookies'  => [],
            'filename' => null,
        ];
    }
}

if (! function_exists('wp_remote_get')) {
    function wp_remote_get(string $url, array $args = [])
    {
        return wp_remote_request($url, wp_parse_args($args, ['method' => 'GET']));
    }
}

if (! function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = [])
    {
        return wp_remote_request($url, array_merge($args, ['method' => 'POST']));
    }
}

if (! function_exists('wp_remote_head')) {
    function wp_remote_head(string $url, array $args = [])
    {
        return wp_remote_request($url, array_merge($args, ['method' => 'HEAD']));
    }
}

if (! function_exists('wp_safe_remote_get')) {
    function wp_safe_remote_get(string $url, array $args = [])
    {
        return wp_remote_get($url, $args);
    }
}

if (! function_exists('wp_safe_remote_post')) {
    function wp_safe_remote_post(string $url, array $args = [])
    {
        return wp_remote_post($url, $args);
    }
}

if (! function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response): string
    {
        return is_wp_error($response) ? '' : (string) ($response['body'] ?? '');
    }
}

if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        return is_wp_error($response) ? '' : ($response['response']['code'] ?? '');
    }
}

if (! function_exists('wp_remote_retrieve_response_message')) {
    function wp_remote_retrieve_response_message($response)
    {
        return is_wp_error($response) ? '' : ($response['response']['message'] ?? '');
    }
}

if (! function_exists('wp_remote_retrieve_headers')) {
    function wp_remote_retrieve_headers($response)
    {
        return is_wp_error($response) ? [] : ($response['headers'] ?? []);
    }
}

if (! function_exists('wp_remote_retrieve_header')) {
    function wp_remote_retrieve_header($response, string $header)
    {
        return wp_remote_retrieve_headers($response)[strtolower($header)] ?? '';
    }
}

if (! function_exists('wp_mail')) {
    function wp_mail($to, string $subject, string $message, $headers = '', $attachments = []): bool
    {
        $email = Config\Services::email();

        $email->setTo(is_array($to) ? implode(',', $to) : $to);
        $email->setFrom(
            (string) (get_option('admin_email', '') ?: 'no-reply@' . parse_url(base_url(), PHP_URL_HOST)),
            (string) get_option('blogname', 'LightCMS')
        );
        $email->setSubject($subject);
        $email->setMessage($message);

        foreach ((array) $attachments as $attachment) {
            $email->attach($attachment);
        }

        foreach ((array) (is_string($headers) ? array_filter(explode("\n", $headers)) : $headers) as $header) {
            if (stripos((string) $header, 'content-type: text/html') !== false) {
                $email->setMailType('html');
            }
        }

        try {
            return $email->send(false);
        } catch (\Throwable $e) {
            log_message('error', 'WP compat: wp_mail failed: ' . $e->getMessage());

            return false;
        }
    }
}

if (! function_exists('wp_get_http_headers')) {
    function wp_get_http_headers(string $url, bool $deprecated = false)
    {
        $response = wp_remote_head($url);

        return is_wp_error($response) ? false : $response['headers'];
    }
}

if (! function_exists('wp_http_validate_url')) {
    function wp_http_validate_url(string $url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) ?: false;
    }
}
