<?php

namespace api\modules\v1\processors;

use api\modules\v1\models\Task;

class DummyImageProcessor implements ImageProcessorInterface
{
    public function process(Task $task): ImageProcessResult
    {
        return new ImageProcessResult(
            $task->input_path,
            filesize($task->input_path)
        );
    }
}
