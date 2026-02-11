<?php

namespace api\modules\v1\services;

use api\modules\v1\models\Asset;
use api\modules\v1\models\Task;
use Yii;
use yii\web\BadRequestHttpException;

class TaskService
{
    /**
     * 基于 Asset 创建批量任务（统一 batch_id）
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

        $batchId = self::generateBatchId();
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
                'batch_id' => $batchId, // ✅ 核心升级
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
            'batch_id' => $batchId,
            'task_ids' => $taskIds,
            'total' => count($taskIds),
        ];
    }

    /**
     * 下载文件名
     */
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

    /**
     * 下载路径
     */
    public static function buildDownloadPath(Task $task): string
    {
        return '/v1/task/download?id=' . $task->id;
    }

    protected static function generateBatchId(): string
    {
        return 'batch_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6);
    }

    protected static function generateTaskId(): string
    {
        return 'task_' . substr(md5(uniqid('', true)), 0, 16);
    }
}
