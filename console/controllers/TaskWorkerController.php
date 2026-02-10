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
     * 原子抢占一个 pending 任务（原子级）
     */
    protected function fetchNextPendingTask(): ?Task
    {
        $now = time();
        $db = Task::getDb();

        return $db->transaction(function ($db) use ($now) {

            // 1️⃣ 原子更新一条 pending 任务为 processing
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
                return null; // 没有 pending 任务
            }

            // 2️⃣ 查询刚刚抢到的任务
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
            // ⏳ 模拟耗时
            // sleep(20);
            // 🎯 模拟输出路径（暂时沿用输入路径，以检验前端生成下载路径URL签名是否正确有效）
            // $outputPath = dirname($task->input_path)
            //     . '/' . basename($task->input_path);

            // // ✅ 计算输出大小
            // $outputSize = is_file($outputPath)
            //     ? filesize($outputPath) * 0.6  // 模拟压缩后大小为原始的 60%
            //     : null;

            // $task->status = 'done';
            // $task->finished_at = time();
            // $task->output_path = $outputPath;
            // $task->output_size = $outputSize;

            // $task->error_message = null;

            $processor = Yii::$container->get(ImageProcessorInterface::class);
            $result = $processor->process($task);

            $task->status = 'done';
            $task->finished_at = time();
            $task->output_path = $result->outputPath;
            $task->output_size = $result->outputSize;
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

            $task->status = 'failed';
            $task->finished_at = time();

            $this->log("Task poisoned & terminated: {$task->id}");

        } else {

            $task->status = 'pending';
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
            ->where(['status' => 'processing'])
            ->andWhere(['<', 'started_at', $timeoutAt])
            ->all();

        foreach ($tasks as $task) {

            $task->retry_count += 1;

            if ($task->retry_count >= $task->max_retry) {

                $task->status = 'failed';
                $task->finished_at = $now;

                $this->log("Stuck task poisoned: {$task->id}");

            } else {

                $task->status = 'pending';
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
