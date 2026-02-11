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
                ->where(['batch_id' => $batch_id]) // ✅ 直接走索引
                ->orderBy(['created_at' => SORT_ASC])
                ->all();

            foreach ($tasks as $task) {

                $snapshot = $this->buildSnapshot($task);
                $key = $task->id;

                $isFinal = in_array($task->status, [
                    Task::STATUS_DONE,
                    Task::STATUS_FAILED,
                ], true);

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

        exit;
    }

    /**
     * 构建 SSE 快照
     */
    protected function buildSnapshot(Task $task): array
    {
        $options = json_decode($task->options, true);
        $assetId = $options['asset_id'] ?? null;
        $snapshot = $options['asset_snapshot'] ?? [];

        return [
            'task_id' => $task->id,
            'asset_id' => $assetId,
            'status' => $task->status,
            'progress' => $this->calcProgress($task),
            'input_size' => $snapshot['size'] ?? null,
            'output_size' => $this->extractOutputSize($task),
            'download_url' => $task->status === Task::STATUS_DONE
                ? TaskService::buildDownloadPath($task)
                : null,
            'error' => $task->error_message,
        ];
    }

    /**
     * 提取输出大小
     */
    protected function extractOutputSize(Task $task): ?int
    {
        if (empty($task->result) || !is_string($task->result)) {
            return null;
        }

        $result = json_decode($task->result, true);

        if (!is_array($result)) {
            return null;
        }

        return $result['output_size'] ?? null;
    }

    /**
     * 进度计算
     */
    protected function calcProgress(Task $task): int
    {
        if ($task->status === Task::STATUS_DONE) {
            return 100;
        }

        if ($task->status !== Task::STATUS_PROCESSING || !$task->started_at) {
            return 0;
        }

        $elapsed = time() - $task->started_at;

        if ($elapsed <= 0) {
            return 0;
        }

        return min(95, max(1, (int)floor($elapsed / 20 * 95)));
    }

    /**
     * 批次状态
     */
    protected function batchStatus(array $tasks): string
    {
        if (empty($tasks)) {
            return 'running';
        }

        $hasDone = false;
        $hasFailed = false;

        foreach ($tasks as $task) {

            if ($task->status === Task::STATUS_DONE) {
                $hasDone = true;
            } elseif ($task->status === Task::STATUS_FAILED) {
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
