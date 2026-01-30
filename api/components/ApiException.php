<?php

namespace api\components;

use yii\web\HttpException;

class ApiException extends HttpException
{
    protected int $apiCode;

    public function __construct(
        int $apiCode,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        $this->apiCode = $apiCode;

        $statusCode = ApiCode::httpStatus($apiCode);

        parent::__construct($statusCode, $message, 0, $previous);
    }

    public function getApiCode(): int
    {
        return $this->apiCode;
    }
}
