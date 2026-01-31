<?php

namespace api\modules\v1\controllers;

use Yii;
use yii\filters\Cors;
use yii\web\Controller;

class ApiController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        /**
         * CORS 必须最先执行
         */
        $behaviors = array_merge([
            'corsFilter' => [
                'class' => Cors::class,
                'cors' => [
                    'Origin' => Yii::$app->params['cors']['origins'] ?? [],
                    'Access-Control-Request-Method' => [
                        'GET', 'POST', 'PUT', 'DELETE', 'OPTIONS',
                    ],
                    'Access-Control-Allow-Headers' => Yii::$app->params['cors']['headers'] ?? [],
                    'Access-Control-Allow-Credentials' => true,
                    'Access-Control-Max-Age' => 86400,
                ],
            ],
        ], $behaviors);

        return $behaviors;
    }

    /**
     * 统一 OPTIONS 处理
     */
    public function actions()
    {
        return [
            'options' => [
                'class' => 'yii\web\OptionsAction',
            ],
        ];
    }

    /**
     * API 默认 JSON
     */
    public function beforeAction($action)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        return parent::beforeAction($action);
    }
}
