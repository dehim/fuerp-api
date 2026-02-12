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

        $usedNames = [];

        foreach ($tasks as $item) {

            $result = json_decode($item->result, true);
            $filePath = $result['output_path'] ?? null;

            if (!$filePath || !is_file($filePath)) {
                continue;
            }

            // 原始目标文件名
            $baseFilename = TaskService::buildDownloadFilename($item);

            // 处理重复
            $finalFilename = $this->resolveDuplicateName($baseFilename, $usedNames);

            $zip->addFile($filePath, $finalFilename);
        }

        $zip->close();

        return new ImageProcessResult(
            $zipPath,
            filesize($zipPath)
        );
    }

    /**
     * 自动处理 ZIP 内重复文件名
     */
    protected function resolveDuplicateName(string $filename, array &$usedNames): string
    {
        if (!isset($usedNames[$filename])) {
            $usedNames[$filename] = 1;

            return $filename;
        }

        $usedNames[$filename]++;

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        $newName = sprintf(
            '%s_%d.%s',
            $baseName,
            $usedNames[$filename],
            $extension
        );

        // 递归防止极端冲突
        return $this->resolveDuplicateName($newName, $usedNames);
    }
}
