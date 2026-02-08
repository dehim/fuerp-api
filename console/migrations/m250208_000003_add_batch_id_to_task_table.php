<?php

use yii\db\Migration;

class m250208_000003_add_batch_id_to_task_table extends Migration
{
    public function up()
    {
        // 一次“开始压缩”产生的任务组
        $this->addColumn('{{%task}}', 'batch_id', $this->char(32)->notNull());

    }

    public function down()
    {
        $this->dropColumn('{{%task}}', 'batch_id');
    }
}
