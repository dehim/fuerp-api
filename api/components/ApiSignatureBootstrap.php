<?php

namespace api\components;

use yii\base\BootstrapInterface;
use yii\web\Application;

class ApiSignatureBootstrap implements BootstrapInterface
{
    public function bootstrap($app)
    {
        $app->on(Application::EVENT_BEFORE_REQUEST, function () use ($app) {

            $request = $app->request;
            $path = trim($request->pathInfo, '/');

            // ====== 全局白名单 ======
            $whitelist = [
                '',
                'v1/site/index',
                'v1/site/ping', // ⬅️ 时间同步 / 健康检查
            ];

            if (in_array($path, $whitelist, true)) {
                return;
            }

            // ====== 模块白名单 ======
            if (
                str_starts_with($path, 'debug') ||
                str_starts_with($path, 'gii')
            ) {
                return;
            }

            // ====== 其余一律先验签 ======
            $context = [
                'method' => $request->method,
                'path' => '/' . $request->pathInfo,
                'params' => $request->getQueryParams(),
            ];

            ApiAuth::checkSignature($context);
        });
    }
}
