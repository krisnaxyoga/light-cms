<?php

use App\Libraries\WordPress\Registry;

/**
 * register_rest_route() plus the helpers around it. Routes are dispatched
 * by Frontend\WordPressRestController at /wp-json/<namespace>/<route>;
 * only the plumbing themes and plugins use for their own endpoints is
 * implemented — the core wp/v2 content endpoints are not emulated.
 */

if (! function_exists('register_rest_route')) {
    function register_rest_route(string $route_namespace, string $route, array $args = [], bool $override = false): bool
    {
        // A single endpoint may be given as one args array or a list of them.
        $endpoints = isset($args['callback']) ? [$args] : $args;

        Registry::$restRoutes[trim($route_namespace, '/') . '/' . ltrim($route, '/')] = [
            'namespace' => trim($route_namespace, '/'),
            'route'     => '/' . ltrim($route, '/'),
            'endpoints' => $endpoints,
        ];

        return true;
    }
}

if (! function_exists('rest_get_routes')) {
    function rest_get_routes(): array
    {
        return Registry::$restRoutes;
    }
}

if (! function_exists('rest_ensure_response')) {
    function rest_ensure_response($response)
    {
        if ($response instanceof WP_REST_Response || $response instanceof WP_Error) {
            return $response;
        }

        return new WP_REST_Response($response);
    }
}

if (! function_exists('rest_authorization_required_code')) {
    function rest_authorization_required_code(): int
    {
        return is_user_logged_in() ? 403 : 401;
    }
}

if (! function_exists('rest_sanitize_boolean')) {
    function rest_sanitize_boolean($value): bool
    {
        return ! in_array(strtolower((string) $value), ['false', '0', '', 'no'], true);
    }
}

if (! function_exists('rest_is_boolean')) {
    function rest_is_boolean($maybe_bool): bool
    {
        return is_bool($maybe_bool) || in_array(strtolower((string) $maybe_bool), ['true', 'false', '0', '1'], true);
    }
}
