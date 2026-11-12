<?php
declare(strict_types=1);

namespace ReleaseHub\Core;

class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Không tìm thấy nội dung yêu cầu.')
    {
        parent::__construct(404, $message, 'not_found');
    }
}
