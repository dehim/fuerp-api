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

        $this->initSseHeaders();

        $lastSnapshot = [];
        $sentFinal = [];

        while (!connection_aborted()) {

            /** @var Task[] $tasks */
            $tasks = Task::find()
                ->where(['batch_id' => $batch_id])
                ->orderBy(['created_at' => SORT_ASC])
                ->all();

            foreach ($tasks as $task) {

                $snapshot = $this->buildSnapshot($task);
                $key = $task->id;

                $isFinal = in_array($task->status, ['done', 'failed'], true);

                if (
                    !isset($lastSnapshot[$key]) ||
                    $lastSnapshot[$key] !== $snapshot ||
                    ($isFinal && empty($sentFinal[$key]))
                ) {
                    $this->sendEvent('task_update', $snapshot);
                    $lastSnapshot[$key] = $snapshot;

                    if ($isFinal) {
                        $sentFinal[$key] = true;
                    }
                }
            }

            $batchStatus = $this->batchStatus($tasks);

            if ($batchStatus !== 'running') {
                $this->sendEvent('batch_done', [
                    'batch_id' => $batch_id,
                    'status' => $batchStatus,
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
            'input_size' => $task->input_size,
            'output_size' => $task->output_size,
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

        $elapsed = time() - $task->started_at;
        if ($elapsed <= 0) {
            return 0;
        }

        return min(95, max(1, (int)floor($elapsed / 20 * 95)));
    }

    protected function batchStatus(array $tasks): string
    {
        if (empty($tasks)) {
            return 'running';
        }

        $hasDone = false;
        $hasFailed = false;

        foreach ($tasks as $task) {
            if ($task->status === 'done') {
                $hasDone = true;
            } elseif ($task->status === 'failed') {
                $hasFailed = true;
            } else {
                return 'running';
            }
        }

        if ($hasDone && !$hasFailed) {
            return 'success';
        }

        if ($hasDone && $hasFailed) {
            return 'partial_failed';
        }

        return 'failed';
    }
}
