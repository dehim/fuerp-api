<?php

namespace api\modules\v1\processors;

use api\modules\v1\models\Task;

interface ImageProcessorInterface
{
    /**
     * @throws \Throwable
     */
    public function process(Task $task): ImageProcessResult;
}
