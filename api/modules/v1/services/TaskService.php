<?php

namespace api\modules\v1\services;

use api\modules\v1\models\Task;
use Yii;
use yii\db\Exception;

class TaskService
{
    /**
     * 创建任务
     *
     * @param array $params
     *
     * @throws Exception
     *
     * @return array
     */
    public static function create(array $params): array
    {
        /**
         * 1️⃣ 参数校验
         */
        if (empty($params['type'])) {
            throw new Exception('Task type is required');
        }

        if (empty($params['input_path'])) {
            throw new Exception('Input path is required');
        }

        $options = $params['options'] ?? null;

        /**
         * 2️⃣ 创建 Task
         */
        $task = new Task();
        $task->id = self::generateTaskId();
        $task->type = $params['type'];
        $task->status = 'pending';
        $task->input_path = $params['input_path'];
        $task->options = $options
            ? json_encode($options, JSON_UNESCAPED_UNICODE)
            : null;
        $task->created_at = time();

        if (!$task->save()) {
            Yii::error($task->getErrors(), 'task.create');

            throw new Exception('Failed to create task');
        }

        /**
         * 3️⃣ 返回结果
         */
        return [
            'task_id' => $task->id,
            'status' => $task->status,
        ];
    }

    /**
     * 生成 task_id
     */
    protected static function generateTaskId(): string
    {
        return md5(uniqid('task_', true));
    }
}
