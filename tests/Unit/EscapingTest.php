<?php
declare(strict_types=1);

namespace ReleaseHub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReleaseHub\Core\View;

/** Kiểm tra XSS ở mức view đại diện: nội dung độc hại phải bị escape. (Không phải trình quét XSS toàn diện.) */
final class EscapingTest extends TestCase
{
    private const PAYLOAD = '<script>alert("xss")</script><img src=x onerror=alert(1)>';

    public function testHelperEscapesEverything(): void
    {
        $out = e(self::PAYLOAD);
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringNotContainsString('<img', $out);
        $this->assertSame('&quot;&#039;&lt;&amp;', e('"\'<&'));
    }

    public function testBadgesEscapeUnknownValues(): void
    {
        $this->assertStringNotContainsString('<script>', status_badge(self::PAYLOAD));
        $this->assertStringNotContainsString('<script>', severity_badge(self::PAYLOAD));
        $this->assertStringNotContainsString('<script>', priority_badge(self::PAYLOAD));
    }

    public function testProjectListViewEscapesProjectFields(): void
    {
        $html = View::render('projects/index', [
            'items' => [['id' => 1, 'code' => 'X', 'name' => self::PAYLOAD, 'description' => self::PAYLOAD, 'color' => '#4f46e5"><script>1</script>', 'icon' => 'bi-x', 'archived_at' => null,
                'owner_name' => self::PAYLOAD, 'my_role' => 'developer', 'issue_total' => 0, 'issue_done' => 0, 'open_bugs' => 0, 'planned_releases' => 0, 'member_count' => 1]],
            'filters' => ['q' => self::PAYLOAD, 'status' => 'active'], 'canCreate' => false,
        ], null);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testErrorViewEscapesMessage(): void
    {
        $html = View::render('errors/error', ['status' => 500, 'message' => self::PAYLOAD], null);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testHistoryTextIsPlainTextForViewToEscape(): void
    {
        $t = history_text(['action' => 'updated', 'changes' => json_encode(['title' => ['a', self::PAYLOAD]])]);
        $this->assertStringNotContainsString('<script>', $t); // chỉ chứa tên trường, không chứa giá trị người dùng
    }
}
