<?php

namespace console\controllers;

use api\modules\v1\models\Task;
use Yii;
use yii\console\Controller;

class TaskWorkerController extends Controller
{
    protected int $lastHeartbeatAt = 0;
    protected int $heartbeatInterval = 3600; // 1 小时

    protected int $lastRecoverAt = 0;
    protected int $recoverInterval = 300; // 5 分钟扫描一次卡死任务

    protected int $taskTimeout = 600; // 任务超时阈值：10 分钟

    /**
     * 启动任务消费
     *
     * ./yii task-worker/run
     */
    public function actionRun()
    {
        $this->log('Task worker started...');

        while (true) {

            // ❤️ 心跳
            $this->heartbeat();

            // ♻️ 卡死任务回收
            $this->recoverStuckTasks();

            // 🚜 拉取下一个 pending 任务
            $task = $this->fetchNextPendingTask();

            if (!$task) {
                // 没任务就歇一会
                sleep(2);
                continue;
            }

            $this->processTask($task);
        }
    }

    /**
     * 原子获取一个 pending 任务（严格版）
     */
    protected function fetchNextPendingTask(): ?Task
    {
        $task = Task::find()
            ->where(['status' => 'pending'])
            ->orderBy(['created_at' => SORT_ASC])
            ->limit(1)
            ->one();

        if (!$task) {
            return null;
        }

        $now = time();

        // 用 id + status 作为条件，保证原子性
        $rows = Task::updateAll(
            [
                'status' => 'processing',
                'started_at' => $now,
            ],
            [
                'id' => $task->id,
                'status' => 'pending',
            ]
        );

        if ($rows === 0) {
            // 被别的 worker 抢走了
            return null;
        }

        // 抢到任务
        return $task;
    }

    /**
     * 处理任务
     */
    protected function processTask(Task $task): void
    {
        $this->log("Processing task: {$task->id}");

        try {
            // ⏳ 模拟耗时任务
            sleep(20);

            $task->status = 'done';
            $task->finished_at = time();
            $task->save(false);

            $this->log("Task finished: {$task->id}");

        } catch (\Throwable $e) {

            $task->status = 'failed';
            $task->error_message = $e->getMessage();
            $task->finished_at = time();
            $task->save(false);

            Yii::error($e->getMessage(), 'task.worker');
            $this->log("Task failed: {$task->id}, error={$e->getMessage()}");
        }
    }

    /**
     * ♻️ 回收卡死任务
     */
    protected function recoverStuckTasks(): void
    {
        $now = time();

        if ($this->lastRecoverAt !== 0 &&
            ($now - $this->lastRecoverAt) < $this->recoverInterval) {
            return;
        }

        $this->lastRecoverAt = $now;

        $timeoutAt = $now - $this->taskTimeout;

        $count = Task::updateAll(
            [
                'status' => 'pending',
                'started_at' => null,
            ],
            [
                'and',
                ['status' => 'processing'],
                ['<', 'started_at', $timeoutAt],
            ]
        );

        if ($count > 0) {
            $this->log("Recovered {$count} stuck task(s)");
        }
    }

    /**
     * ❤️ 心跳日志
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

    /**
     * 日志输出
     */
    protected function log(string $message): void
    {
        $time = date('Y-m-d H:i:s');
        echo "[{$time}] {$message}\n";
    }
}
