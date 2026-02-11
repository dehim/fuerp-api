<?php

namespace api\modules\v1\models;

use Yii;

/**
 * This is the model class for table "asset".
 *
 * @property string $id
 * @property string $type
 * @property string $storage_disk
 * @property string $storage_path
 * @property string|null $storage_hash
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property string|null $extension
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property int $is_temporary
 * @property int|null $expires_at
 * @property int|null $deleted_at
 * @property int $created_at
 * @property string|null $created_ip
 */
class Asset extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'asset';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['storage_hash', 'original_name', 'mime_type', 'extension', 'width', 'height', 'expires_at', 'deleted_at', 'created_ip'], 'default', 'value' => null],
            [['storage_disk'], 'default', 'value' => 'local'],
            [['is_temporary'], 'default', 'value' => 1],
            [['id', 'type', 'storage_path', 'size', 'created_at'], 'required'],
            [['storage_path'], 'string'],
            [['size', 'width', 'height', 'is_temporary', 'expires_at', 'deleted_at', 'created_at'], 'integer'],
            [['id'], 'string', 'max' => 32],
            [['type', 'storage_disk'], 'string', 'max' => 50],
            [['storage_hash'], 'string', 'max' => 64],
            [['original_name'], 'string', 'max' => 255],
            [['mime_type'], 'string', 'max' => 100],
            [['extension'], 'string', 'max' => 20],
            [['created_ip'], 'string', 'max' => 45],
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
            'storage_disk' => 'Storage Disk',
            'storage_path' => 'Storage Path',
            'storage_hash' => 'Storage Hash',
            'original_name' => 'Original Name',
            'mime_type' => 'Mime Type',
            'extension' => 'Extension',
            'size' => 'Size',
            'width' => 'Width',
            'height' => 'Height',
            'is_temporary' => 'Is Temporary',
            'expires_at' => 'Expires At',
            'deleted_at' => 'Deleted At',
            'created_at' => 'Created At',
            'created_ip' => 'Created Ip',
        ];
    }

}
