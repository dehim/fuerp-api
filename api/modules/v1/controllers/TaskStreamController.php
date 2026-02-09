<?php

namespace api\modules\v1\controllers;

use api\modules\v1\models\Task;
use Yii;
use yii\web\BadRequestHttpException;

class TaskStreamController extends ApiController
{
    public $enableCsrfValidation = false;

    /**
     * GET /v1/task/stream?batch_id=xxx
     */
    public function actionIndex(string $batch_id)
    {
        if (empty($batch_id)) {
            throw new BadRequestHttpException('batch_id is required');
        }

        // SSE headers
        Yii::$app->response->headers->set('Content-Type', 'text/event-stream');
        Yii::$app->response->headers->set('Cache-Control', 'no-cache');
        Yii::$app->response->headers->set('Connection', 'keep-alive');
        Yii::$app->response->headers->set('X-Accel-Buffering', 'no'); // nginx

        @ob_end_flush();
        @ob_implicit_flush(true);

        // 记录上一次推送的状态
        $lastSnapshot = [];

        while (!connection_aborted()) {

            /** @var Task[] $tasks */
            $tasks = Task::find()
                ->where(['batch_id' => $batch_id])
                ->orderBy(['created_at' => SORT_ASC])
                ->all();

            foreach ($tasks as $task) {

                $snapshot = $this->buildSnapshot($task);

                $key = $task->id;

                if (!isset($lastSnapshot[$key]) ||
                    $lastSnapshot[$key] !== $snapshot) {

                    $this->sendEvent($snapshot);
                    $lastSnapshot[$key] = $snapshot;
                }
            }

            // 所有任务结束 → 自动关闭 SSE
            if ($this->allTasksFinished($tasks)) {
                $this->sendEvent(['type' => 'batch_done']);
                break;
            }

            sleep(1);
        }

        Yii::$app->end();
    }

    protected function buildSnapshot(Task $task): array
    {
        return [
            'image_id' => $task->image_id,
            'status' => $task->status,
            'progress' => $this->calcProgress($task),
            'output_path' => $task->output_path,
            'error' => $task->error_message,
        ];
    }

    protected function calcProgress(Task $task): int
    {
        if ($task->status === 'done') {
            return 100;
        }

        if ($task->status !== 'processing' || !$task->started_at) {
            return 0;
        }

        // ⏳ 模拟进度：10 分钟内线性增长
        $elapsed = time() - $task->started_at;

        return min(95, intval($elapsed / 600 * 100));
    }

    protected function sendEvent(array $data): void
    {
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }

    protected function allTasksFinished(array $tasks): bool
    {
        foreach ($tasks as $task) {
            if (!in_array($task->status, ['done', 'failed'], true)) {
                return false;
            }
        }

        return true;
    }
}
