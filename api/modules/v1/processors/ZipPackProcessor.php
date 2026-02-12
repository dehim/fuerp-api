<?php

namespace api\modules\v1\processors;

use api\modules\v1\models\Task;
use api\modules\v1\models\Task as TaskModel;
use api\modules\v1\services\TaskService;
use Yii;
use ZipArchive;

class ZipPackProcessor implements ImageProcessorInterface
{
    public function process(Task $task): ImageProcessResult
    {
        $options = json_decode($task->options, true);

        $sourceBatchId = $options['source_batch_id'] ?? null;

        if (!$sourceBatchId) {
            throw new \RuntimeException('source_batch_id missing');
        }

        // 查询源批次已完成任务
        $tasks = TaskModel::find()
            ->where([
                'batch_id' => $sourceBatchId,
                'status' => TaskModel::STATUS_DONE,
                'type' => TaskModel::TYPE_IMAGE_COMPRESS,
            ])
            ->all();

        if (!$tasks) {
            throw new \RuntimeException('No completed tasks in batch');
        }

        $zipDir = Yii::getAlias('@runtime/pack');
        if (!is_dir($zipDir)) {
            mkdir($zipDir, 0777, true);
        }

        $zipPath = $zipDir . '/' . $task->id . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create zip');
        }

        foreach ($tasks as $item) {

            $result = json_decode($item->result, true);

            $filePath = $result['output_path'] ?? null;

            if (!$filePath || !is_file($filePath)) {
                continue;
            }

            // ✅ 关键修改：使用统一命名规则
            $filenameInZip = TaskService::buildDownloadFilename($item);

            $zip->addFile($filePath, $filenameInZip);
        }

        $zip->close();

        return new ImageProcessResult(
            $zipPath,
            filesize($zipPath)
        );
    }
}
