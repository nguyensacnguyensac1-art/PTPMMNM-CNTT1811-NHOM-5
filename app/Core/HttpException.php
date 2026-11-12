<?php
declare(strict_types=1);

namespace ReleaseHub\Core;

class HttpException extends \RuntimeException
{
    /** @param array<string,mixed> $extra */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly string $errorCode = 'error',
        public readonly array $extra = []
    ) {
        parent::__construct($message, $status);
    }
}
