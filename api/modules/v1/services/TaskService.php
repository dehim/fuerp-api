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
                    'original_name' => $asset->original_name,
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
     * ✅ 构建下载文件名
     * 原文件名 + _compressed + 扩展名
     */
    public static function buildDownloadFilename(Task $task): string
    {
        $result = json_decode($task->result, true);
        $options = json_decode($task->options, true);

        if (!$result || empty($result['output_extension'])) {
            return 'compressed.jpg';
        }

        $extension = $result['output_extension'];

        $originalName = $options['asset_snapshot']['original_name'] ?? null;

        if (!$originalName) {
            return sprintf(
                'compressed_%s.%s',
                $task->id,
                $extension
            );
        }

        // 去掉原扩展名
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);

        // 文件名安全过滤（防止奇怪字符）
        $baseName = self::sanitizeFilename($baseName);

        return sprintf(
            '%s_compressed.%s',
            $baseName,
            $extension
        );
    }

    /**
     * 文件名安全处理
     */
    protected static function sanitizeFilename(string $name): string
    {
        // 去除非法字符
        $name = preg_replace('/[^\pL\pN\-_]/u', '_', $name);

        // 避免连续下划线
        $name = preg_replace('/_+/', '_', $name);

        return trim($name, '_');
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
