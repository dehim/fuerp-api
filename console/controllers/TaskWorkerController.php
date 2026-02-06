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
        $this->log('Task worker started...');

        while (true) {

            // 心跳检测
            $this->heartbeat();

            // 1️⃣ 查找一个 pending 任务
            /** @var Task|null $task */
            $task = Task::find()
                ->where(['status' => 'pending'])
                ->orderBy(['created_at' => SORT_ASC])
                ->one();

            if (!$task) {
                // 没任务就歇一会
                sleep(5);
                continue;
            }

            // 2️⃣ 原子抢占任务
            $rows = Task::updateAll(
                [
                    'status' => 'processing',
                    'started_at' => time(),
                ],
                [
                    'id' => $task->id,
                    'status' => 'pending',
                ]
            );

            if ($rows === 0) {
                // 被别的 worker 抢走了
                continue;
            }

            // 3️⃣ 真正处理任务
            $this->processTask($task->id);
        }
    }

    protected function processTask(string $taskId): void
    {
        $this->log("Processing task: {$taskId}");

        /** @var Task $task */
        $task = Task::findOne(['id' => $taskId]);

        if (!$task) {
            $this->log("Task {$taskId} not found");

            return;
        }

        try {
            // ⏳ 模拟耗时任务（图片处理）
            sleep(20);

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
