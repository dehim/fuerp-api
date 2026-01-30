<?php

namespace api\components;

use Yii;
use yii\web\Response;

class ApiResponse
{
    public static function success($data = null, string $message = 'ok'): Response
    {
        return self::send(ApiCode::SUCCESS, $message, $data);
    }

    public static function fail(int $code, string $message, $data = null): Response
    {
        return self::send($code, $message, $data);
    }

    protected static function send(int $code, string $message, $data): Response
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;

        // ✅ 唯一来源：ApiCode
        $response->statusCode = ApiCode::httpStatus($code);

        $response->data = [
            'success' => $code === ApiCode::SUCCESS,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];

        return $response;
    }
}
