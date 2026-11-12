<?php
declare(strict_types=1);

namespace ReleaseHub\Core;

final class Router
{
    /** @var array<int,array{method:string,regex:string,handler:array{0:class-string,1:string},public:bool}> */
    private array $routes = [];

    /** @param array{0:class-string,1:string} $handler */
    public function add(string $method, string $pattern, array $handler, bool $public = false): void
    {
        $regex = preg_replace('#\{([a-z_]+)\}#i', '(?P<$1>[^/]+)', $pattern);
        $regex = preg_replace('#\{([a-z_]+):([^}]+)\}#i', '(?P<$1>$2)', (string) $regex);
        $this->routes[] = ['method' => $method, 'regex' => '#^' . $regex . '$#', 'handler' => $handler, 'public' => $public];
    }

    /** @param array{0:class-string,1:string} $handler */
    public function get(string $p, array $h, bool $public = false): void
    {
        $this->add('GET', $p, $h, $public);
    }

    /** @param array{0:class-string,1:string} $handler */
    public function post(string $p, array $h, bool $public = false): void
    {
        $this->add('POST', $p, $h, $public);
    }

    /** @return array{handler:array{0:class-string,1:string},params:array<string,string>,public:bool}|null */
    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $r) {
            if ($r['method'] === $method && preg_match($r['regex'], $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                return ['handler' => $r['handler'], 'params' => $params, 'public' => $r['public']];
            }
        }
        return null;
    }

    public function pathExists(string $path): bool
    {
        foreach ($this->routes as $r) {
            if (preg_match($r['regex'], $path)) {
                return true;
            }
        }
        return false;
    }
}
