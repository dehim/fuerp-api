<?php

namespace api\components;

use Yii;
use yii\web\ErrorHandler;
use yii\web\HttpException;
use yii\web\Response;

class ApiErrorHandler extends ErrorHandler
{
    protected function renderException($exception): void
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;

        // 默认兜底
        $code = ApiCode::UNKNOWN_ERROR;
        $message = '系统繁忙，请稍后再试';

        if ($exception instanceof ApiException) {
            // ✅ 业务 & API 异常（最优先）
            $code = $exception->getApiCode();
            $message = $exception->getMessage();
        } elseif ($exception instanceof HttpException) {
            // ✅ Yii / 路由 / Method Not Allowed 等
            $code = match ($exception->statusCode) {
                400 => ApiCode::PARAM_INVALID,
                401 => ApiCode::UNAUTHORIZED,
                403 => ApiCode::FORBIDDEN,
                404 => ApiCode::NOT_FOUND,
                405 => ApiCode::METHOD_NOT_ALLOWED,
                default => ApiCode::SYSTEM_ERROR,
            };
            $message = $exception->getMessage();
        } else {
            // ✅ 未捕获异常 → 系统错误
            $code = ApiCode::SYSTEM_ERROR;

            Yii::error($exception, 'api.exception');
        }

        // ✅ HTTP 状态的唯一来源
        $response->statusCode = ApiCode::httpStatus($code);

        $response->data = [
            'success' => false,
            'code' => $code,
            'message' => $message,
            'path' => Yii::$app->request->getUrl(),
        ];

        if (YII_DEBUG) {
            $response->data['exception'] = [
                'type' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        $response->send();
    }
}
