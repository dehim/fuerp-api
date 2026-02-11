<?php

namespace api\modules\v1\processors;

use api\modules\v1\models\Task;

class DummyImageProcessor implements ImageProcessorInterface
{
    public function process(Task $task): ImageProcessResult
    {
        $raw = json_decode($task->options, true);

        if (!$raw || !isset($raw['asset_snapshot']['storage_path'])) {
            throw new \RuntimeException("Invalid task options");
        }

        $inputPath = $raw['asset_snapshot']['storage_path'];

        return new ImageProcessResult(
            $inputPath,
            filesize($inputPath)
        );
    }
}
