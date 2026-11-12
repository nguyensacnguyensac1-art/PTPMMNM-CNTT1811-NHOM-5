<?php
declare(strict_types=1);

namespace ReleaseHub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReleaseHub\Core\ValidationException;
use ReleaseHub\Domain\Catalog;
use ReleaseHub\Support\Validate;

final class ValidateTest extends TestCase
{
    private function fails(callable $fn): array
    {
        $v = new Validate();
        $fn($v);
        try {
            $v->throwIfFailed();
        } catch (ValidationException $e) {
            return $e->errors;
        }
        return [];
    }

    public function testUrlAcceptsOnlyHttpAndHttps(): void
    {
        foreach (['https://github.com/a/b/pull/1', 'http://example.com/x'] as $ok) {
            $this->assertSame([], $this->fails(fn (Validate $v) => $v->url(['u' => $ok], 'u', 'URL')));
        }
        foreach (['javascript:alert(1)', 'data:text/html,<b>', 'file:///etc/passwd', 'ftp://x.y/z', 'https://', 'https://a b.com', '//evil.com'] as $bad) {
            $this->assertArrayHasKey('u', $this->fails(fn (Validate $v) => $v->url(['u' => $bad], 'u', 'URL')), $bad);
        }
    }

    public function testDateEnumAndStringLimits(): void
    {
        $this->assertArrayHasKey('d', $this->fails(fn (Validate $v) => $v->date(['d' => '2026-02-30'], 'd', 'Ngày')));
        $this->assertArrayHasKey('d', $this->fails(fn (Validate $v) => $v->date(['d' => '31/12/2026'], 'd', 'Ngày')));
        $this->assertSame([], $this->fails(fn (Validate $v) => $v->date(['d' => '2028-02-29'], 'd', 'Ngày')));
        $this->assertArrayHasKey('p', $this->fails(fn (Validate $v) => $v->enum(['p' => 'extreme'], 'p', 'Ưu tiên', Catalog::PRIORITIES)));
        $this->assertArrayHasKey('t', $this->fails(fn (Validate $v) => $v->str(['t' => 'ab'], 't', 'Tiêu đề', 3, 10, true)));
        $this->assertArrayHasKey('t', $this->fails(fn (Validate $v) => $v->str(['t' => str_repeat('x', 11)], 't', 'Tiêu đề', 3, 10, true)));
        $this->assertArrayHasKey('t', $this->fails(fn (Validate $v) => $v->str([], 't', 'Tiêu đề', 0, 10, true)));
    }
}
