<?php

use yii\db\Migration;

class m250202_000001_create_task_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%task}}', [
            'id' => $this->char(32)->notNull(),
            'type' => $this->string(32)->notNull(),
            'status' => $this->string(16)->notNull(),
            'input_path' => $this->text()->notNull(),
            'output_path' => $this->text(),
            'options' => $this->text(),
            'error_message' => $this->text(),
            'created_at' => $this->integer()->notNull(),
            'started_at' => $this->integer(),
            'finished_at' => $this->integer(),
            'PRIMARY KEY(id)',
        ], $tableOptions);

        $this->createIndex('idx_task_status', '{{%task}}', 'status');
        $this->createIndex('idx_task_created_at', '{{%task}}', 'created_at');
    }

    public function safeDown()
    {
        $this->dropTable('{{%task}}');
    }
}
