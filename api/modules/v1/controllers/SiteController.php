<?php

namespace api\modules\v1\controllers;

use api\components\ApiResponse;
use api\modules\v1\controllers\base\ApiController;
use Yii;

class SiteController extends ApiController
{
    /**
     * GET / POST /v1/site/ping
     *
     * 不验签接口：
     * - 用于前端时间同步
     * - 用于健康检查
     */
    public function actionPing()
    {
        $serverTime = time();

        $request = Yii::$app->request;

        // 尝试从 POST JSON 中获取 local_time
        $localTime = null;

        if ($request->isPost) {
            $body = $request->getBodyParams();
            if (isset($body['local_time']) && is_numeric($body['local_time'])) {
                $localTime = (int)$body['local_time'];
            }
        }

        // 若未提供本地时间，则认为客户端时间 == 服务器时间
        $offset = 0;
        if ($localTime !== null) {
            $offset = $serverTime - $localTime;
        }

        return ApiResponse::success([
            'server_time' => $serverTime,
            'timezone' => date_default_timezone_get(),
            'iso' => gmdate('c', $serverTime),
            'offset' => $offset, // 单位：秒（server - client）
        ]);
    }

    /**
     * GET /v1/site/index
     */
    public function actionIndex()
    {
        return ApiResponse::success('api is running');
    }
}
