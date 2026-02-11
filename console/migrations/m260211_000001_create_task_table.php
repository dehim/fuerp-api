<?php

use yii\db\Migration;

/**
 * 任务表
 */
class m260211_000001_create_task_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%task}}', [
            'id' => $this->char(32)->notNull()->append('PRIMARY KEY'),
            'batch_id' => $this->string(64)->notNull(),
            'type' => $this->string(32)->notNull(),
            'status' => $this->string(16)->notNull(),
            'options' => $this->text(),
            'result' => $this->text(),
            'error_message' => $this->text(),
            'created_at' => $this->integer()->notNull(),
            'started_at' => $this->integer(),
            'finished_at' => $this->integer(),
            'retry_count' => $this->integer(),
            'max_retry' => $this->integer(),
        ], $tableOptions);

        $this->createIndex('idx_task_status', '{{%task}}', 'status');
        $this->createIndex('idx_task_created_at', '{{%task}}', 'created_at');
        $this->createIndex('idx_task_status_created_at', '{{%task}}', ['status', 'created_at']);
        $this->createIndex('idx_task_finished_at', '{{%task}}', 'finished_at');

        if ($this->db->driverName === 'mysql') {
            $this->addCommentOnColumn('{{%task}}', 'id', '任务唯一ID（32位字符串，UUID）');
            $this->addCommentOnColumn('{{%task}}', 'type', '任务类型：image_compress / batch_zip / future_xxx');
            $this->addCommentOnColumn('{{%task}}', 'status', '任务状态：pending / processing / done / failed');
            // ... 更多注释
        }

    }

    public function safeDown()
    {
        $this->dropTable('{{%task}}');
    }
}
