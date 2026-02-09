<?php

namespace api\modules\v1\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

class ApiSseController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * SSE 专用 Controller
     */
    public function behaviors()
    {
        // 只保留 OPTIONS 预检
        return [];
    }

    public function actions()
    {
        return [
            'options' => [
                'class' => 'yii\web\OptionsAction',
            ],
        ];
    }

    public function beforeAction($action)
    {
        // 关闭 JSON / format
        Yii::$app->response->format = Response::FORMAT_RAW;

        // 禁用 zlib 压缩
        if (function_exists('ini_set')) {
            ini_set('zlib.output_compression', 'Off');
        }

        // 阻止 Yii 再次设置 header
        Yii::$app->response->headers->removeAll();

        return parent::beforeAction($action);
    }

    /**
     * 初始化 SSE 响应头
     */
    protected function initSseHeaders(): void
    {
        $request = Yii::$app->request;
        $response = Yii::$app->response;

        // 🚨 禁用 Yii 自动格式化 & 清空 header
        $response->format = \yii\web\Response::FORMAT_RAW;
        $response->headers->removeAll();

        // -----------------------------
        // ✅ CORS 动态判断
        // -----------------------------
        $allowedOrigins = Yii::$app->params['cors']['origins'] ?? [];
        $origin = $request->getHeaders()->get('Origin');

        if ($origin && in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            $allowedHeaders = Yii::$app->params['cors']['headers'] ?? ['Content-Type'];
            header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));
        }

        // -----------------------------
        // ✅ SSE 必要头
        // -----------------------------
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // -----------------------------
        // 清空 PHP 输出缓冲
        // -----------------------------
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        ob_implicit_flush(true);
    }

    /**
     * SSE 事件发送工具
     */
    protected function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }
}
