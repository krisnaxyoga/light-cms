<?php

namespace App\Controllers\Frontend;

use App\Controllers\BaseController;
use App\Libraries\WordPress\Exceptions\WordPressJsonException;
use App\Libraries\WordPress\Registry;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Dispatcher for routes a theme or plugin registered with
 * register_rest_route(), served at /wp-json/<namespace>/<route>.
 *
 * Core's own wp/v2 endpoints are not emulated — LightCMS has its own
 * content APIs — so a request for one returns 404 with a clear message
 * instead of silently returning nothing.
 */
class WordPressRestController extends BaseController
{
    public function dispatch(string ...$segments)
    {
        lcms_wp_boot(lcms_wp_active() ? 'theme' : 'plugins');

        // Routes are registered on this hook, exactly as in WordPress.
        \App\Libraries\WordPress\Hooks::doAction('rest_api_init');

        $path   = trim(implode('/', $segments), '/');
        $method = strtoupper($this->request->getMethod());

        [$route, $params] = $this->match($path);

        if ($route === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'code'    => 'rest_no_route',
                'message' => str_starts_with($path, 'wp/v2')
                    ? 'The core wp/v2 REST endpoints are not implemented by the LightCMS WordPress compatibility layer.'
                    : 'No route was found matching the URL and request method.',
                'data'    => ['status' => 404],
            ]);
        }

        $endpoint = $this->endpointFor($route, $method);

        if ($endpoint === null) {
            return $this->response->setStatusCode(405)->setJSON([
                'code'    => 'rest_no_route',
                'message' => 'The handler for the route is invalid for this method.',
                'data'    => ['status' => 405],
            ]);
        }

        $request = new WP_REST_Request($method, '/' . $path, $endpoint);
        $request->set_body((string) $this->request->getBody());

        foreach ($this->request->headers() as $name => $header) {
            $request->set_header((string) $name, $this->request->getHeaderLine((string) $name));
        }

        foreach (array_merge(
            $this->request->getGet() ?? [],
            $this->request->getPost() ?? [],
            $request->get_json_params(),
            $params
        ) as $key => $value) {
            $request->set_param((string) $key, $value);
        }

        // Defaults declared in the route's `args` definition.
        foreach ($endpoint['args'] ?? [] as $name => $definition) {
            if (! $request->has_param((string) $name) && array_key_exists('default', (array) $definition)) {
                $request->set_param((string) $name, $definition['default']);
            }
        }

        $permission = $endpoint['permission_callback'] ?? null;

        if ($permission !== null && $permission !== '__return_true') {
            $allowed = is_callable($permission) ? $permission($request) : (bool) $permission;

            if ($allowed instanceof WP_Error) {
                return $this->errorResponse($allowed, 403);
            }

            if (! $allowed) {
                return $this->response->setStatusCode(is_user_logged_in() ? 403 : 401)->setJSON([
                    'code'    => 'rest_forbidden',
                    'message' => 'Sorry, you are not allowed to do that.',
                    'data'    => ['status' => is_user_logged_in() ? 403 : 401],
                ]);
            }
        }

        try {
            $result = ($endpoint['callback'])($request);
        } catch (WordPressJsonException $e) {
            return $this->response->setStatusCode($e->statusCode())->setJSON($e->data());
        } catch (Throwable $e) {
            log_message('critical', 'WP compat REST: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'code'    => 'internal_error',
                'message' => ENVIRONMENT === 'production' ? 'Handler error.' : $e->getMessage(),
                'data'    => ['status' => 500],
            ]);
        }

        if ($result instanceof WP_Error) {
            return $this->errorResponse($result, 400);
        }

        if ($result instanceof WP_REST_Response) {
            $response = $this->response->setStatusCode($result->get_status())->setJSON($result->get_data());

            foreach ($result->get_headers() as $name => $value) {
                $response->setHeader((string) $name, (string) $value);
            }

            return $response;
        }

        return $this->response->setJSON($result);
    }

    /**
     * @return array{0: array|null, 1: array<string, string>}
     */
    protected function match(string $path): array
    {
        foreach (Registry::$restRoutes as $key => $route) {
            $pattern = '#^' . $route['namespace'] . rtrim($route['route'], '/') . '/?$#';

            if (preg_match($pattern, $path, $matches) === 1) {
                $params = [];

                foreach ($matches as $name => $value) {
                    if (is_string($name)) {
                        $params[$name] = $value;
                    }
                }

                return [$route, $params];
            }
        }

        return [null, []];
    }

    protected function endpointFor(array $route, string $method): ?array
    {
        foreach ($route['endpoints'] as $endpoint) {
            $methods = $endpoint['methods'] ?? 'GET';
            $methods = is_array($methods) ? $methods : (preg_split('/[\s,]+/', (string) $methods) ?: []);
            $methods = array_map('strtoupper', $methods);

            if (in_array($method, $methods, true) && isset($endpoint['callback']) && is_callable($endpoint['callback'])) {
                return $endpoint;
            }
        }

        return null;
    }

    protected function errorResponse(WP_Error $error, int $fallbackStatus)
    {
        $data   = $error->get_error_data();
        $status = is_array($data) && isset($data['status']) ? (int) $data['status'] : $fallbackStatus;

        return $this->response->setStatusCode($status)->setJSON([
            'code'    => $error->get_error_code(),
            'message' => $error->get_error_message(),
            'data'    => ['status' => $status],
        ]);
    }
}
