<?php

/**
 * A small slice of the REST infrastructure — enough for
 * register_rest_route() handlers, which is how modern classic themes
 * expose their own AJAX endpoints. Routes are dispatched by
 * Frontend\WordPressRestController at /wp-json/<namespace>/<route>.
 */
if (! class_exists('WP_REST_Request')) {
    #[AllowDynamicProperties]
    class WP_REST_Request implements ArrayAccess
    {
        protected array $params = [];
        protected array $headers = [];
        protected string $body = '';

        public function __construct(protected string $method = 'GET', protected string $route = '', protected array $attributes = [])
        {
        }

        public function get_method(): string
        {
            return $this->method;
        }

        public function set_method(string $method): void
        {
            $this->method = strtoupper($method);
        }

        public function get_route(): string
        {
            return $this->route;
        }

        public function get_params(): array
        {
            return $this->params;
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }

        public function set_param(string $key, mixed $value): void
        {
            $this->params[$key] = $value;
        }

        public function has_param(string $key): bool
        {
            return array_key_exists($key, $this->params);
        }

        public function get_json_params(): array
        {
            $decoded = json_decode($this->body, true);

            return is_array($decoded) ? $decoded : [];
        }

        public function get_body(): string
        {
            return $this->body;
        }

        public function set_body(string $body): void
        {
            $this->body = $body;
        }

        public function get_headers(): array
        {
            return $this->headers;
        }

        public function get_header(string $key): ?string
        {
            return $this->headers[strtolower(str_replace('_', '-', $key))] ?? null;
        }

        public function set_header(string $key, string $value): void
        {
            $this->headers[strtolower(str_replace('_', '-', $key))] = $value;
        }

        public function get_attributes(): array
        {
            return $this->attributes;
        }

        public function offsetExists(mixed $offset): bool
        {
            return isset($this->params[$offset]);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->params[$offset] ?? null;
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            $this->params[$offset] = $value;
        }

        public function offsetUnset(mixed $offset): void
        {
            unset($this->params[$offset]);
        }
    }
}

if (! class_exists('WP_REST_Response')) {
    #[AllowDynamicProperties]
    class WP_REST_Response
    {
        public function __construct(protected mixed $data = null, protected int $status = 200, protected array $headers = [])
        {
        }

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function set_data(mixed $data): void
        {
            $this->data = $data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        public function set_status(int $status): void
        {
            $this->status = $status;
        }

        public function get_headers(): array
        {
            return $this->headers;
        }

        public function header(string $key, string $value): void
        {
            $this->headers[$key] = $value;
        }
    }
}

if (! class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
        public const READABLE  = 'GET';
        public const CREATABLE = 'POST';
        public const EDITABLE  = 'POST, PUT, PATCH';
        public const DELETABLE = 'DELETE';
        public const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
    }
}
