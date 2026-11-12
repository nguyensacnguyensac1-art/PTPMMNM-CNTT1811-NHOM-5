<?php
declare(strict_types=1);

namespace ReleaseHub\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ReleaseHub\Core\Env;

/**
 * Kiểm tra THỰC TẾ qua Apache: tệp nội bộ phải bị từ chối. Chỉ chạy khi đặt APP_TEST_URL, ví dụ:
 *   APP_TEST_URL=http://localhost/releasehub  (không có "/public" ở cuối)
 * Nếu không đặt, test được ĐÁNH DẤU BỎ QUA (không tính là đã kiểm chứng).
 */
final class ApacheAccessTest extends TestCase
{
    private function status(string $url): int
    {
        $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10, 'follow_location' => 0]]);
        @file_get_contents($url, false, $ctx);
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                return (int) $m[1];
            }
        }
        return 0;
    }

    /** @return array<string,array{0:string}> */
    public function internalFiles(): array
    {
        $files = ['.env', '.env.example', 'database/releasehub.sql', 'database/migrations/001_create_schema.sql', 'storage/logs/app.log', 'composer.json', 'composer.lock',
            'phpunit.xml', 'app/Core/Env.php', 'app/routes.php', 'vendor/autoload.php', 'tests/bootstrap.php', 'bin/seed.php', 'views/layouts/app.php', 'docs/architecture.md', 'README.md', 'public/.htaccess'];
        return array_combine($files, array_map(static fn ($f) => [$f], $files));
    }

    /** @dataProvider internalFiles */
    public function testInternalFileIsDenied(string $file): void
    {
        $base = Env::get('APP_TEST_URL');
        if (!$base) {
            $this->markTestSkipped('Chưa đặt APP_TEST_URL (cần Apache đang chạy).');
        }
        $code = $this->status(rtrim($base, '/') . '/' . $file);
        $this->assertContains($code, [403, 404], "{$file} phải bị từ chối nhưng nhận HTTP {$code}");
    }

    public function testPublicEntryIsReachable(): void
    {
        $base = Env::get('APP_TEST_URL');
        if (!$base) {
            $this->markTestSkipped('Chưa đặt APP_TEST_URL.');
        }
        $this->assertSame(200, $this->status(rtrim($base, '/') . '/public/login'));
        $this->assertSame(200, $this->status(rtrim($base, '/') . '/public/assets/css/app.css'));
    }
}
