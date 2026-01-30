<?php

namespace api\exceptions;

use api\components\ApiCode;
use api\components\ApiException;

class BusinessException extends ApiException
{
    public function __construct(
        int $code = ApiCode::BUSINESS_ERROR,
        string $message = '业务处理失败',
        ?\Throwable $previous = null
    ) {
        parent::__construct($code, $message, $previous);
    }
}
