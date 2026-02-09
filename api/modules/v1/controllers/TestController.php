<?php

namespace api\modules\v1\controllers;

use api\components\ApiCode;
use api\components\ApiResponse;
use api\exceptions\BusinessException;
use api\modules\v1\controllers\base\ApiController;
use Yii;

class TestController extends ApiController
{
    public function actionIndex()
    {
        return ApiResponse::success([
            'service' => 'fuerp-api',
            'version' => 'v1',
        ]);
    }

    public function actionView($id)
    {
        if (!$id) {
            throw new BusinessException(ApiCode::PARAM_MISSING, 'id 不能为空');
        }

        return ApiResponse::success([
            'id' => $id,
        ]);
    }

    /**
     * 获取当前访问者真实 IP（用于排查代理 / CDN / Docker 链路）
     */
    public function actionIp()
    {
        $request = Yii::$app->request;

        return ApiResponse::success([
            // Yii2 认定的最终用户 IP（业务应使用这个）
            'user_ip' => $request->getUserIP(),

            // PHP / Nginx 层最终看到的 REMOTE_ADDR
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,

            // 代理相关头，仅用于排查
            'headers' => [
                'x_forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
                'x_real_ip' => $_SERVER['HTTP_X_REAL_IP'] ?? null,
                'cf_connecting_ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            ],
        ]);
    }
}
