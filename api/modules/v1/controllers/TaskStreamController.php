<?php

namespace api\modules\v1\controllers;

use api\modules\v1\models\Task;
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

        // ✅ 初始化 SSE 头，包含跨域
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
                $this->sendEvent('batch_done', ['batch_id' => $batch_id]);
                break;
            }

            sleep(1);
        }

        // ⚠️ SSE 必须直接退出
        exit;
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

        $elapsed = time() - $task->started_at;

        return min(95, (int)($elapsed / 600 * 100));
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
