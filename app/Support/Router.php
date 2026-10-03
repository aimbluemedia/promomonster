<?php

declare(strict_types=1);

namespace App\Support;

final class Router
{
    /** @var array<string,array<string,callable>> */
    private array $routes = ['GET' => [], 'POST' => []];

    /**
     * Routes that end in a single value, like /r/{token}.
     *
     * Kept separate from the exact routes rather than turning dispatch into a
     * pattern matcher: every path on this site is either fixed or fixed-plus-
     * one-token, and a prefix table stays readable and stays fast. Exact routes
     * are still checked first, so /r/{token} can never shadow a real page.
     *
     * @var array<string,array<string,callable>>
     */
    private array $prefixes = ['GET' => [], 'POST' => []];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    /**
     * Register /prefix/{value}. The handler is called with the value, already
     * URL-decoded, and is responsible for validating it.
     */
    public function getToken(string $prefix, callable $handler): void
    {
        $this->prefixes['GET'][rtrim($prefix, '/') . '/'] = $handler;
    }

    public function postToken(string $prefix, callable $handler): void
    {
        $this->prefixes['POST'][rtrim($prefix, '/') . '/'] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = '/' . trim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');

        // When the document root is the project root rather than public/, the
        // root .htaccess rewrites into public/ and the request URI keeps that
        // prefix. Strip it so routes match under either layout.
        if ($path === '/public' || str_starts_with($path, '/public/')) {
            $path = '/' . ltrim(substr($path, 7), '/');
        }
        $method = strtoupper($method);

        // HEAD is GET without a body; PHP discards the body for us.
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            foreach ($this->prefixes[$method] ?? [] as $prefix => $prefixed) {
                if (!str_starts_with($path, $prefix)) {
                    continue;
                }
                $value = substr($path, strlen($prefix));
                // One segment only. A token with a slash in it is not a token,
                // it is someone probing, and it must not reach the handler.
                if ($value === '' || str_contains($value, '/')) {
                    break;
                }
                $prefixed(rawurldecode($value));
                return;
            }
        }

        if ($handler === null) {
            // A path that exists under another verb is 405, not 404.
            foreach ($this->routes as $verb => $paths) {
                if ($verb !== $method && isset($paths[$path])) {
                    http_response_code(405);
                    header('Allow: ' . $verb);
                    echo View::page('errors/404', ['title' => 'Method not allowed']);
                    return;
                }
            }
            http_response_code(404);
            echo View::page('errors/404', ['title' => 'Page not found']);
            return;
        }

        $handler();
    }
}
