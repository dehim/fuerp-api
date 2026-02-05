<?php

namespace console\controllers;

use api\modules\v1\models\Task;
use Yii;
use yii\console\Controller;

class TaskWorkerController extends Controller
{
    protected int $lastHeartbeatAt = 0;
    protected int $heartbeatInterval = 3600; // 1 小时

    /**
     * 启动任务消费
     *
     * ./yii task-worker/run
     */
    public function actionRun()
    {
        $redis = Yii::$app->redis;
        $queueKey = 'task:queue';

        $this->log('Task worker started...');

        while (true) {

            // 心跳检测
            $this->heartbeat();

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
                $this->log("Invalid task payload: {$payloadJson}");
                continue;
            }

            $this->processTask($taskId);
        }
    }

    protected function processTask(string $taskId): void
    {

        /** @var Task|null $task */
        $task = Task::findOne(['id' => $taskId]);

        // 检查任务状态，防止重复处理
        if ($task->status !== 'pending') {
            $this->log("Skip task {$taskId}, status={$task->status}");

            return;
        }

        $this->log("Processing task: {$taskId}");

        if (!$task) {
            $this->log("Task not found: {$taskId}");

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

            $this->log("Task finished: {$taskId}");

        } catch (\Throwable $e) {
            $task->status = 'failed';
            $task->error_message = $e->getMessage();
            $task->finished_at = time();
            $task->save(false);

            Yii::error($e->getMessage(), 'task.worker');

            $this->log("Task failed: {$taskId}, error={$e->getMessage()}");
        }
    }

    protected function log(string $message): void
    {
        $time = date('Y-m-d H:i:s');
        echo "[{$time}] {$message}\n";
    }

    /**
     * 心跳日志
     */
    protected function heartbeat(): void
    {
        $now = time();

        if ($this->lastHeartbeatAt === 0) {
            $this->lastHeartbeatAt = $now;

            return;
        }

        if (($now - $this->lastHeartbeatAt) >= $this->heartbeatInterval) {
            $this->log('Worker heartbeat: alive');
            $this->lastHeartbeatAt = $now;
        }
    }

}
