<?php

namespace api\modules\v1\services;

use api\modules\v1\models\Task;
use Yii;
use yii\db\Exception;

class TaskService
{
    public static function create(array $params): array
    {
        if (empty($params['type'])) {
            throw new Exception('Task type is required');
        }

        if (empty($params['input_path'])) {
            throw new Exception('Input path is required');
        }

        $task = new Task();
        $task->id = self::generateTaskId();
        $task->type = $params['type'];
        $task->status = 'pending';
        $task->input_path = $params['input_path'];
        $task->options = isset($params['options'])
            ? json_encode($params['options'], JSON_UNESCAPED_UNICODE)
            : null;

        $task->retry_count = 0;
        $task->max_retry = 3;
        $task->created_at = time();

        if (!$task->save()) {
            Yii::error($task->getErrors(), 'task.create');

            throw new Exception('Failed to create task');
        }

        return [
            'task_id' => $task->id,
            'status' => $task->status,
        ];
    }

    protected static function generateTaskId(): string
    {
        return md5(uniqid('task_', true));
    }
}
