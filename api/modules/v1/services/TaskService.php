<?php

namespace api\modules\v1\services;

use api\modules\v1\models\Asset;
use api\modules\v1\models\Task;
use Yii;
use yii\web\BadRequestHttpException;

class TaskService
{
    /**
     * 基于 Asset 创建任务（无 batch_id）
     *
     * @param Asset[] $assets
     * @param array   $options
     */
    public static function createBatchFromAssets(array $assets, array $options): array
    {
        if (empty($assets)) {
            throw new BadRequestHttpException('Assets cannot be empty');
        }

        if (empty($options)) {
            throw new BadRequestHttpException('Options is required');
        }

        $now = time();
        $rows = [];
        $taskIds = [];

        foreach ($assets as $asset) {

            if (!$asset instanceof Asset) {
                throw new BadRequestHttpException('Invalid asset object');
            }

            if ($asset->type !== 'image') {
                throw new BadRequestHttpException("Unsupported asset type: {$asset->type}");
            }

            $taskId = self::generateTaskId();

            /**
             * 🔥 关键：
             * 把 asset_id 放进 options
             */
            $taskOptions = [
                'asset_id' => $asset->id,
                'asset_snapshot' => [
                    'storage_disk' => $asset->storage_disk,
                    'storage_path' => $asset->storage_path,
                    'size' => $asset->size,
                    'width' => $asset->width,
                    'height' => $asset->height,
                ],
                'process_options' => $options,
            ];

            $rows[] = [
                'id' => $taskId,
                'type' => 'image_compress',
                'status' => Task::STATUS_PENDING,
                'options' => json_encode($taskOptions, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'retry_count' => 0,
                'max_retry' => 3,
            ];

            $taskIds[] = $taskId;
        }

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();

        try {

            $db->createCommand()
                ->batchInsert(
                    Task::tableName(),
                    array_keys($rows[0]),
                    $rows
                )
                ->execute();

            $transaction->commit();

        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        return [
            'task_ids' => $taskIds,
            'total' => count($taskIds),
        ];
    }

    public static function buildDownloadFilename(Task $task): string
    {
        $result = json_decode($task->result, true);

        if (!$result || empty($result['output_extension'])) {
            return 'compressed.jpg';
        }

        return sprintf(
            'compressed_%s.%s',
            $task->id,
            $result['output_extension']
        );
    }

    public static function buildDownloadPath(Task $task): string
    {
        return '/v1/task/download?id=' . $task->id;
    }

    protected static function generateTaskId(): string
    {
        return 'task_' . substr(md5(uniqid('', true)), 0, 16);
    }
}
