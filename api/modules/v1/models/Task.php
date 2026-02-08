<?php

namespace api\modules\v1\models;

/**
 * This is the model class for table "task".
 *
 * @property string $id
 * @property string $type
 * @property string $status
 * @property string $input_path
 * @property string|null $output_path
 * @property string|null $options
 * @property string|null $error_message
 * @property int $created_at
 * @property int|null $started_at
 * @property int|null $finished_at
 * @property int $retry_count
 * @property int $max_retry
 * @property string $batch_id
 * @property string $image_id
 */
class Task extends \yii\db\ActiveRecord
{
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
            [['output_path', 'options', 'error_message', 'started_at', 'finished_at'], 'default', 'value' => null],
            [['retry_count'], 'default', 'value' => 0],
            [['max_retry'], 'default', 'value' => 3],
            [['id', 'batch_id', 'image_id', 'type', 'status', 'input_path', 'created_at'], 'required'],
            [['input_path', 'output_path', 'options', 'error_message'], 'string'],
            [['created_at', 'started_at', 'finished_at','retry_count','max_retry'], 'integer'],
            [['id', 'batch_id', 'image_id', 'type'], 'string', 'max' => 32],
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
            'type' => 'Type',
            'status' => 'Status',
            'input_path' => 'Input Path',
            'output_path' => 'Output Path',
            'options' => 'Options',
            'error_message' => 'Error Message',
            'created_at' => 'Created At',
            'started_at' => 'Started At',
            'finished_at' => 'Finished At',
            'retry_count' => 'Retry Count',
            'max_retry' => 'Max Retry',
            'batch_id' => 'Batch ID',
            'image_id' => 'Image ID',
        ];
    }

}
