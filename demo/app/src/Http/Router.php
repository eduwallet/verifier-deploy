<?php

namespace Demo\Http;

use Demo\ApiException;

final class Router
{
    /** @var list<array{method:string,regex:string,handler:callable}> */
    private array $routes = [];

    /** @param callable(Request,array<string,string>):Response $handler */
    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = preg_replace('/\{(\w+)\}/', '(?<$1>[A-Za-z0-9_-]+)', $pattern);
        $this->routes[] = ['method' => $method, 'regex' => '#^' . $regex . '$#', 'handler' => $handler];
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] === $request->method) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return ($route['handler'])($request, $params);
            }
        }
        throw $pathMatched
            ? new ApiException(405, 'method_not_allowed', 'Method not allowed')
            : new ApiException(404, 'not_found', 'Not found');
    }
}
