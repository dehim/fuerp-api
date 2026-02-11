<?php

use yii\db\Migration;

/**
 * 存储所有上传或系统生成的文件资源元数据表
 */
class m260210_000001_create_asset_table extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        // 创建资源表
        $this->createTable('{{%asset}}', [
            // 主键：资源唯一ID（32位字符串）
            'id' => $this->char(32)->notNull()->append('PRIMARY KEY'),

            // 资源类型
            'type' => $this->string(50)->notNull(),

            // 存储类型
            'storage_disk' => $this->string(50)->notNull()->defaultValue('local'),

            // 实际存储路径
            'storage_path' => $this->text()->notNull(),

            // 文件内容hash
            'storage_hash' => $this->string(64),

            // 用户原始文件名
            'original_name' => $this->string(255),

            // MIME类型
            'mime_type' => $this->string(100),

            // 文件扩展名
            'extension' => $this->string(20),

            // 文件大小（字节）
            'size' => $this->bigInteger()->notNull(),

            // 图片宽度
            'width' => $this->integer(),

            // 图片高度
            'height' => $this->integer(),

            // 是否为临时文件
            'is_temporary' => $this->tinyInteger()->notNull()->defaultValue(1),

            // 过期时间
            'expires_at' => $this->integer(),

            // 软删除时间
            'deleted_at' => $this->integer(),

            // 创建时间
            'created_at' => $this->integer()->notNull(),

            // 上传来源IP
            'created_ip' => $this->string(45),
        ], $tableOptions);

        // 创建索引
        $this->createIndex('idx_asset_storage_hash', '{{%asset}}', 'storage_hash');
        $this->createIndex('idx_asset_expires_at', '{{%asset}}', 'expires_at');
        $this->createIndex('idx_asset_created_at', '{{%asset}}', 'created_at');
        $this->createIndex('idx_asset_deleted_at', '{{%asset}}', 'deleted_at');

        // 复合索引：按临时状态和过期时间查询
        $this->createIndex('idx_asset_temporary_expires', '{{%asset}}', ['is_temporary', 'expires_at']);

        // 按类型和时间查询
        $this->createIndex('idx_asset_type_created', '{{%asset}}', ['type', 'created_at']);

        if ($this->db->driverName === 'mysql') {
            $this->addCommentOnTable('{{%asset}}', '文件资源元数据表');
            $this->addCommentOnColumn('{{%asset}}', 'id', '资源唯一ID（32位字符串，UUID）');
            $this->addCommentOnColumn('{{%asset}}', 'type', '资源类型：image / zip / future');
            $this->addCommentOnColumn('{{%asset}}', 'storage_disk', '存储类型：local / s3 / oss');
            $this->addCommentOnColumn('{{%asset}}', 'is_temporary', '是否为临时文件（1=是，0=否）');
        }

    }

    public function down()
    {
        $this->dropTable('{{%asset}}');
    }
}
