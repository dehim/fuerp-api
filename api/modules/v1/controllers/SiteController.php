<?php

namespace api\modules\v1\controllers;

use yii\web\Controller;

class SiteController extends Controller
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

}
