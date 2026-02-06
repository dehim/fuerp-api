<?php

use yii\db\Migration;

class m250206_000002_add_retry_count_to_task_table extends Migration
{
    public function up()
    {
        // 已尝试次数（防止毒任务）
        $this->addColumn('{{%task}}', 'retry_count', $this->integer()->notNull()->defaultValue(0));

        // 允许的最大重试次数（可按任务类型调整）
        $this->addColumn('{{%task}}', 'max_retry', $this->integer()->notNull()->defaultValue(3));
    }

    public function down()
    {
        $this->dropColumn('{{%task}}', 'retry_count');
        $this->dropColumn('{{%task}}', 'max_retry');
    }
}
