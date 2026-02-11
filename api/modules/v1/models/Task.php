<?php

namespace api\modules\v1\models;

/**
 * This is the model class for table "task".
 *
 * @property string $id
 * @property string $batch_id
 * @property string $type
 * @property string $status
 * @property string|null $options
 * @property string|null $result
 * @property string|null $error_message
 * @property int $created_at
 * @property int|null $started_at
 * @property int|null $finished_at
 * @property int|null $retry_count
 * @property int|null $max_retry
 */
class Task extends \yii\db\ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'task';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['options', 'result', 'error_message', 'started_at', 'finished_at', 'retry_count', 'max_retry'], 'default', 'value' => null],
            [['id', 'batch_id', 'type', 'status', 'created_at'], 'required'],
            [['options', 'result', 'error_message'], 'string'],
            [['created_at', 'started_at', 'finished_at', 'retry_count', 'max_retry'], 'integer'],
            [['id', 'type'], 'string', 'max' => 32],
            [['batch_id'], 'string', 'max' => 64],
            [['status'], 'string', 'max' => 16],
            [['id'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'batch_id' => 'Batch ID',
            'type' => 'Type',
            'status' => 'Status',
            'options' => 'Options',
            'result' => 'Result',
            'error_message' => 'Error Message',
            'created_at' => 'Created At',
            'started_at' => 'Started At',
            'finished_at' => 'Finished At',
            'retry_count' => 'Retry Count',
            'max_retry' => 'Max Retry',
        ];
    }

}
