<?php
declare(strict_types=1);

namespace ReleaseHub\Support;

/** DB lưu UTC; giao diện hiển thị giờ Việt Nam (UTC+7). */
final class Time
{
    public const TZ = 'Asia/Ho_Chi_Minh';

    public static function vn(?string $utc, string $format = 'd/m/Y H:i'): string
    {
        if ($utc === null || $utc === '') {
            return '—';
        }
        try {
            $d = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
            return $d->setTimezone(new \DateTimeZone(self::TZ))->format($format);
        } catch (\Exception) {
            return $utc;
        }
    }

    public static function vnDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));
        return $d ? $d->format('d/m/Y') : $date;
    }
}
