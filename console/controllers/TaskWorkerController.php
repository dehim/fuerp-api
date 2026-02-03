<?php

namespace console\controllers;

use api\modules\v1\models\Task;
use Yii;
use yii\console\Controller;

class TaskWorkerController extends Controller
{
    /**
     * 启动任务消费
     *
     * ./yii task-worker/run
     */
    public function actionRun()
    {
        $redis = Yii::$app->redis;
        $queueKey = 'task:queue';

        echo "Task worker started...\n";

        while (true) {
            // 阻塞弹出任务，timeout 5秒
            $data = $redis->brpop($queueKey, 5);

            // 安全检查
            if (!$data || !is_array($data) || count($data) < 2) {
                continue;
            }

            $key = $data[0];
            $payloadJson = $data[1];

            // JSON 解码
            $payload = json_decode($payloadJson, true);
            $taskId = $payload['task_id'] ?? null;

            if (!$taskId) {
                echo "Invalid task payload: {$payloadJson}\n";
                continue;
            }

            $this->processTask($taskId);
        }
    }

    protected function processTask(string $taskId): void
    {
        echo "Processing task: {$taskId}\n";

        /** @var Task|null $task */
        $task = Task::findOne(['id' => $taskId]);

        if (!$task) {
            echo "Task not found: {$taskId}\n";

            return;
        }

        try {
            // 标记开始
            $task->status = 'processing';
            $task->started_at = time();
            $task->save(false);

            /**
             * ⏳ v1 阶段：模拟耗时任务
             */
            sleep(1);

            // 标记完成
            $task->status = 'done';
            $task->finished_at = time();
            $task->save(false);

            echo "Task finished: {$taskId}\n";

        } catch (\Throwable $e) {
            $task->status = 'failed';
            $task->error_message = $e->getMessage();
            $task->finished_at = time();
            $task->save(false);

            Yii::error($e->getMessage(), 'task.worker');

            echo "Task failed: {$taskId}\n";
        }
    }
}
