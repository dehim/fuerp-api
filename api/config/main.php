<?php

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'fuerp-api',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'api\\controllers',
    'bootstrap' => ['log', api\components\ApiSignatureBootstrap::class],
    'modules' => [
        'v1' => [
            'class' => 'api\\modules\\v1\\Module',
        ],
        'debug' => [
            'class' => 'yii\\debug\\Module',
            'allowedIPs' => ['*'], // 本地/内网调试，生产环境记得收紧
        ],
    ],
    'components' => [
        'request' => [
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => 'yii\\web\\JsonParser',
            ],
        ],

        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
            'charset' => 'UTF-8',
        ],

        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                // ✅ 显式版本（优先）
                'v<version:\d+>/<controller>/<action>' => 'v<version>/<controller>/<action>',

                // ✅ 默认版本（无 v 前缀）
                // '<controller>/<action>' => 'v1/<controller>/<action>',

                // ✅ 根路径 /
                '' => 'v1/site/index',

            ],
        ],

        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\\log\\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],

        'errorHandler' => [
            'class' => api\components\ApiErrorHandler::class,
        ],

    ],
    'params' => $params,
];
