<?php
declare(strict_types=1);

namespace ReleaseHub\Core;

class ConflictException extends HttpException
{
    /** @param array<string,mixed> $extra */
    public function __construct(string $message, string $errorCode = 'conflict', array $extra = [])
    {
        parent::__construct(409, $message, $errorCode, $extra);
    }
}
