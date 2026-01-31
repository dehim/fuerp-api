<?php

namespace api\modules\v1\controllers;

use api\components\ApiResponse;

class SiteController extends ApiController
{
    /**
     * GET /v1/site/ping
     *
     * 不验签接口：
     * - 用于前端时间同步
     * - 用于健康检查
     */
    public function actionPing()
    {
        $now = time();

        return ApiResponse::success([
            'server_time' => $now,
            'timezone' => date_default_timezone_get(),
            'iso' => gmdate('c', $now),
        ]);
    }

    /**
     * GET /v1/site/index
     * （你原来的默认首页）
     */
    public function actionIndex()
    {
        return ApiResponse::success('api is running');
    }
}
