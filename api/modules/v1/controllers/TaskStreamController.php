<?php

namespace api\modules\v1\controllers;

use api\modules\v1\controllers\base\ApiSseController;
use api\modules\v1\models\Task;
use api\modules\v1\services\TaskService;
use yii\web\BadRequestHttpException;

class TaskStreamController extends ApiSseController
{
    /**
     * GET /v1/task-stream?batch_id=xxx
     */
    public function actionIndex(string $batch_id)
    {
        if (empty($batch_id)) {
            throw new BadRequestHttpException('batch_id is required');
        }

        // ✅ 初始化 SSE 头（含 CORS / no-buffer）
        $this->initSseHeaders();

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

                if (!isset($lastSnapshot[$key]) || $lastSnapshot[$key] !== $snapshot) {
                    $this->sendEvent('task_update', $snapshot);
                    $lastSnapshot[$key] = $snapshot;
                }
            }

            if ($this->allTasksFinished($tasks)) {
                $this->sendEvent('batch_done', [
                    'batch_id' => $batch_id,
                ]);
                break;
            }

            sleep(1);
        }

        // ⚠️ SSE 请求必须直接退出
        exit;
    }

    /**
     * 构建 SSE 快照
     */
    protected function buildSnapshot(Task $task): array
    {
        return [
            'image_id' => $task->image_id,
            'status' => $task->status,
            'progress' => $this->calcProgress($task),

            // 🎯 仅在完成时返回下载入口
            'download_url' => $task->status === 'done'
                ? TaskService::buildDownloadPath($task)
                : null,

            'error' => $task->error_message,
        ];
    }

    /**
     * 模拟进度计算
     */
    protected function calcProgress(Task $task): int
    {
        // ✅ 已完成：直接 100%
        if ($task->status === 'done') {
            return 100;
        }

        // ❌ 非处理中 or 未开始
        if ($task->status !== 'processing' || !$task->started_at) {
            return 0;
        }

        // ⏱️ 模拟任务总耗时（秒）
        $totalSeconds = 20;

        $elapsed = time() - $task->started_at;

        // ⛔ 防止异常值
        if ($elapsed <= 0) {
            return 0;
        }

        // 📈 进度计算：最多到 95%
        $progress = (int)floor($elapsed / $totalSeconds * 95);

        return min(95, max(1, $progress));
    }

    /**
     * 判断批次是否全部完成
     */
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
