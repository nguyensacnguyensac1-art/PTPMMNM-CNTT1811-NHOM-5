<?php
declare(strict_types=1);

namespace ReleaseHub\Core;

final class Request
{
    /** @param array<string,mixed> $query @param array<string,mixed> $body @param array<string,string> $headers */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $basePath,
        public readonly array $query,
        public readonly array $body,
        public readonly array $headers,
        public readonly string $ip = ''
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uriPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uriPath = rawurldecode($uriPath);
        $base = self::detectBasePath();
        $path = $uriPath;
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
        }
        $path = '/' . trim($path, '/');

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = (string) $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        $body = $_POST;
        if (str_contains($headers['content-type'] ?? '', 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $body = is_array($decoded) ? $decoded : [];
        }
        return new self($method, $path, $base, $_GET, $body, $headers, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    /**
     * Thư mục con chứa front controller, ví dụ /releasehub/public. Chỉ tin SCRIPT_NAME khi nó thật sự là index.php
     * (máy chủ tích hợp của PHP đôi khi đặt SCRIPT_NAME = đường dẫn yêu cầu có đuôi tệp).
     */
    public static function detectBasePath(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        if (basename($script) !== 'index.php') {
            return '';
        }
        $base = rtrim(dirname($script), '/');
        return ($base === '.' || $base === '\\') ? '' : $base;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function wantsJson(): bool
    {
        return str_starts_with($this->path, '/api/')
            || str_contains($this->header('accept') ?? '', 'application/json')
            || $this->header('x-requested-with') === 'XMLHttpRequest';
    }
}
