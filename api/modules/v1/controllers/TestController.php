<?php

namespace api\modules\v1\controllers;

use api\components\ApiCode;
use api\components\ApiResponse;
use api\exceptions\BusinessException;
use yii\web\Controller;

class TestController extends Controller
{
    public function actionIndex()
    {
        return [
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'service' => 'fuerp-api',
                'version' => 'v1',
            ],
        ];
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
}
