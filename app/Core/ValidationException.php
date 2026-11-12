<?php
declare(strict_types=1);

namespace ReleaseHub\Core;

class ValidationException extends HttpException
{
    /** @param array<string,string> $errors */
    public function __construct(public readonly array $errors, string $message = 'Dữ liệu không hợp lệ.')
    {
        parent::__construct(422, $message, 'validation_failed', ['errors' => $errors]);
    }
}
