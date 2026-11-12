<?php
declare(strict_types=1);

namespace ReleaseHub\Support;

use ReleaseHub\Core\ValidationException;

/** Bộ kiểm tra đầu vào phía máy chủ; gom lỗi theo trường rồi ném ValidationException. */
final class Validate
{
    /** @var array<string,string> */
    private array $errors = [];

    public function str(array $in, string $key, string $label, int $min = 0, int $max = 255, bool $required = false): ?string
    {
        $v = $in[$key] ?? null;
        $v = is_scalar($v) ? trim((string) $v) : '';
        if ($v === '') {
            if ($required || $min > 0) {
                $this->errors[$key] = "{$label} là bắt buộc.";
            }
            return null;
        }
        $len = mb_strlen($v);
        if ($len < $min) {
            $this->errors[$key] = "{$label} phải có ít nhất {$min} ký tự.";
        } elseif ($len > $max) {
            $this->errors[$key] = "{$label} tối đa {$max} ký tự.";
        }
        return $v;
    }

    /** @param array<string,string> $allowed */
    public function enum(array $in, string $key, string $label, array $allowed, ?string $default = null, bool $required = false): ?string
    {
        $v = $in[$key] ?? null;
        $v = is_scalar($v) ? trim((string) $v) : '';
        if ($v === '') {
            if ($required && $default === null) {
                $this->errors[$key] = "{$label} là bắt buộc.";
            }
            return $default;
        }
        if (!array_key_exists($v, $allowed)) {
            $this->errors[$key] = "{$label} không hợp lệ.";
            return $default;
        }
        return $v;
    }

    public function date(array $in, string $key, string $label): ?string
    {
        $v = $in[$key] ?? null;
        $v = is_scalar($v) ? trim((string) $v) : '';
        if ($v === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v || (int) $d->format('Y') < 2000 || (int) $d->format('Y') > 2100) {
            $this->errors[$key] = "{$label} phải có dạng YYYY-MM-DD hợp lệ.";
            return null;
        }
        return $v;
    }

    /** Chỉ chấp nhận http/https (chặn javascript:, data:, file:...). */
    public function url(array $in, string $key, string $label): ?string
    {
        $v = $this->str($in, $key, $label, 0, 500);
        if ($v === null) {
            return null;
        }
        $parts = parse_url($v);
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host']) || preg_match('/\s/', $v)) {
            $this->errors[$key] = "{$label} phải là đường dẫn http(s) hợp lệ.";
            return null;
        }
        return $v;
    }

    public function intId(array $in, string $key, string $label, bool $required = false): ?int
    {
        $v = $in[$key] ?? null;
        if ($v === null || $v === '' || $v === 'none') {
            if ($required) {
                $this->errors[$key] = "{$label} là bắt buộc.";
            }
            return null;
        }
        if (!is_numeric($v) || (int) $v <= 0 || (string) (int) $v !== (string) $v && (float) $v != (int) $v) {
            $this->errors[$key] = "{$label} không hợp lệ.";
            return null;
        }
        return (int) $v;
    }

    public function fail(string $key, string $message): void
    {
        $this->errors[$key] = $message;
    }

    public function has(string $key): bool
    {
        return isset($this->errors[$key]);
    }

    public function throwIfFailed(): void
    {
        if ($this->errors) {
            throw new ValidationException($this->errors);
        }
    }
}
