<?php

namespace console\controllers;

use api\modules\v1\models\Task;
use api\modules\v1\processors\ImageProcessorInterface;
use Yii;
use yii\console\Controller;

class TaskWorkerController extends Controller
{
    protected int $lastHeartbeatAt = 0;
    protected int $heartbeatInterval = 3600; // 1 小时

    protected int $lastRecoverAt = 0;
    protected int $recoverInterval = 300; // 5 分钟

    protected int $taskTimeout = 600; // 10 分钟

    /**
     * ./yii task-worker/run
     */
    public function actionRun()
    {
        $this->log('Task worker started...');

        while (true) {

            $this->heartbeat();

            $this->recoverStuckTasks();

            $task = $this->fetchNextPendingTask();

            if (!$task) {
                sleep(2);
                continue;
            }

            $this->processTask($task);
        }
    }

    /**
     * 原子抢占一个 pending 任务
     */
    protected function fetchNextPendingTask(): ?Task
    {
        $now = time();
        $db = Task::getDb();

        return $db->transaction(function ($db) use ($now) {

            // 1️⃣ 抢占一条 pending 任务
            $rows = $db->createCommand("
                UPDATE task
                SET status = :status, started_at = :started_at
                WHERE id = (
                    SELECT id FROM task
                    WHERE status = 'pending'
                    ORDER BY created_at ASC
                    LIMIT 1
                )
            ", [
                ':status' => 'processing',
                ':started_at' => $now,
            ])->execute();

            if ($rows === 0) {
                return null;
            }

            // 2️⃣ 查询刚抢到的任务
            $task = Task::find()
                ->where(['status' => 'processing', 'started_at' => $now])
                ->one();

            return $task;
        });
    }

    /**
     * 执行任务
     */
    protected function processTask(Task $task): void
    {
        $this->log("Processing task: {$task->id}, retry={$task->retry_count}");

        try {
            // 解析 options
            $options = json_decode($task->options, true);

            if (!$options || !isset($options['asset_snapshot'])) {
                throw new \RuntimeException("Task options invalid for task {$task->id}");
            }

            // 交给 ImageProcessorInterface 处理
            $processor = Yii::$container->get(ImageProcessorInterface::class);

            $resultData = $processor->process($task);

            if (!isset($resultData->outputPath) || !isset($resultData->outputSize)) {
                throw new \RuntimeException("Processor returned invalid result for task {$task->id}");
            }

            // 将结果写入 result
            $task->status = Task::STATUS_DONE;
            $task->finished_at = time();
            $task->result = json_encode([
                'output_path' => $resultData->outputPath,
                'output_size' => $resultData->outputSize,
                'output_extension' => pathinfo($resultData->outputPath, PATHINFO_EXTENSION),
            ], JSON_UNESCAPED_UNICODE);
            $task->error_message = null;

            $task->save(false);

            $this->log("Task finished: {$task->id}");

        } catch (\Throwable $e) {
            $this->handleTaskFailure($task, $e->getMessage());
        }
    }

    /**
     * 处理失败 / 重试 / 毒任务终结
     */
    protected function handleTaskFailure(Task $task, string $error): void
    {
        $task->retry_count += 1;
        $task->error_message = $error;

        if ($task->retry_count >= $task->max_retry) {

            $task->status = Task::STATUS_FAILED;
            $task->finished_at = time();

            $this->log("Task poisoned & terminated: {$task->id}");

        } else {

            $task->status = Task::STATUS_PENDING;
            $task->started_at = null;

            $this->log("Task retry scheduled: {$task->id}, retry={$task->retry_count}");
        }

        $task->save(false);

        Yii::error($error, 'task.worker');
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

        /** @var Task[] $tasks */
        $tasks = Task::find()
            ->where(['status' => Task::STATUS_PROCESSING])
            ->andWhere(['<', 'started_at', $timeoutAt])
            ->all();

        foreach ($tasks as $task) {

            $task->retry_count += 1;

            if ($task->retry_count >= $task->max_retry) {

                $task->status = Task::STATUS_FAILED;
                $task->finished_at = $now;

                $this->log("Stuck task poisoned: {$task->id}");

            } else {

                $task->status = Task::STATUS_PENDING;
                $task->started_at = null;

                $this->log("Recovered stuck task: {$task->id}, retry={$task->retry_count}");
            }

            $task->save(false);
        }
    }

    /**
     * ❤️ 心跳
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

    protected function log(string $message): void
    {
        echo '[' . date('Y-m-d H:i:s') . "] {$message}\n";
    }
}
