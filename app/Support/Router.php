<?php

declare(strict_types=1);

namespace App\Support;

final class Router
{
    /** @var array<string,array<string,callable>> */
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
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
