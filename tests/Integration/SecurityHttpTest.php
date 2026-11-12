<?php
declare(strict_types=1);

namespace ReleaseHub\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ReleaseHub\Core\Env;

/**
 * Kiểm tra CSRF/xác thực qua HTTP thật. Chỉ gửi yêu cầu KHÔNG ghi dữ liệu (bị từ chối trước khi ghi) nên an toàn với DB thật.
 * Chỉ chạy khi đặt APP_TEST_URL (VD http://localhost/releasehub); nếu không sẽ BỎ QUA.
 */
final class SecurityHttpTest extends TestCase
{
    /** @return array{0:int,1:string} */
    private function post(string $path, array $headers = [], string $body = '{}'): array
    {
        $base = Env::get('APP_TEST_URL');
        if (!$base) {
            $this->markTestSkipped('Chưa đặt APP_TEST_URL.');
        }
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'ignore_errors' => true, 'timeout' => 10, 'follow_location' => 0,
            'header' => implode("\r\n", array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers)), 'content' => $body]]);
        $res = (string) @file_get_contents(rtrim($base, '/') . '/public/' . ltrim($path, '/'), false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $code = (int) $m[1];
            }
        }
        return [$code, $res];
    }

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        foreach (['api/issues/1', 'api/projects/1/issues', 'api/releases/1/finalize', 'api/dependencies/1/delete', 'login'] as $p) {
            [$code] = $this->post($p);
            $this->assertSame(403, $code, "POST {$p} không có CSRF phải bị 403");
        }
    }

    public function testPostWithForgedCsrfTokenIsRejected(): void
    {
        [$code, $body] = $this->post('api/issues/1', ['X-CSRF-Token: ' . str_repeat('a', 64)]);
        $this->assertSame(403, $code);
        $this->assertStringContainsString('csrf_failed', $body);
    }

    public function testAnonymousPageRedirectsToLogin(): void
    {
        $base = Env::get('APP_TEST_URL');
        if (!$base) {
            $this->markTestSkipped('Chưa đặt APP_TEST_URL.');
        }
        $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0]]);
        @file_get_contents(rtrim($base, '/') . '/public/projects/1', false, $ctx);
        $this->assertStringContainsString(' 302', (string) ($http_response_header[0] ?? ''));
        $this->assertNotEmpty(array_filter($http_response_header ?? [], fn ($h) => stripos($h, 'Location:') === 0 && str_contains($h, 'login')));
    }
}
