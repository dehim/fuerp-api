<?php

namespace api\modules\v1\services;

use api\modules\v1\models\Task;
use Yii;
use yii\web\BadRequestHttpException;

class TaskService
{
    /**
     * 批量创建压缩任务
     */
    public static function createBatch(array $params): array
    {
        $images = $params['images'] ?? [];
        $options = $params['options'] ?? null;

        if (empty($images) || !is_array($images)) {
            throw new BadRequestHttpException('images is required');
        }

        if ($options === null) {
            throw new BadRequestHttpException('options is required');
        }

        $batchId = self::generateBatchId();
        $now = time();

        $rows = [];
        $imageIds = [];

        foreach ($images as $img) {
            if (
                empty($img['image_id']) ||
                empty($img['input_path']) ||
                !isset($img['size'])
            ) {
                throw new BadRequestHttpException('Invalid image payload');
            }

            $rows[] = [
                'id' => self::generateTaskId(),
                'batch_id' => $batchId,
                'type' => 'image_compress',
                'status' => 'pending',
                'image_id' => $img['image_id'],
                'input_path' => $img['input_path'],
                'input_size' => (int)$img['size'], // ✅ 原始大小
                'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'retry_count' => 0,
                'max_retry' => 3,
            ];

            $imageIds[] = $img['image_id'];
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
            'image_ids' => $imageIds,
        ];
    }

    public static function buildDownloadFilename(Task $task): string
    {
        // 示例： image_123_compressed.jpg
        $ext = pathinfo($task->output_path, PATHINFO_EXTENSION);

        return sprintf(
            'image_%s_compressed.%s',
            $task->image_id,
            $ext ?: 'jpg'
        );
    }

    /**
     * ✅ 构建【相对】下载路径（给 SSE 用）
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
