<?php

use yii\db\Migration;

class m250208_000004_add_image_id_to_task_table extends Migration
{
    public function up()
    {
        // 一次“开始压缩”产生的任务组
        $this->addColumn('{{%task}}', 'image_id', $this->char(32)->notNull());

    }

    public function down()
    {
        $this->dropColumn('{{%task}}', 'image_id');
    }
}
