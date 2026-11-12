<?php
declare(strict_types=1);

namespace ReleaseHub\Core;

class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Bạn không có quyền thực hiện thao tác này.')
    {
        parent::__construct(403, $message, 'forbidden');
    }
}
