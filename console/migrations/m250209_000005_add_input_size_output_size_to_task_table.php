<?php

use yii\db\Migration;

class m250209_000005_add_input_size_output_size_to_task_table extends Migration
{
    public function up()
    {
        // 原始大小，bytes
        $this->addColumn('{{%task}}', 'input_size', $this->bigInteger->defaultValue(null));
        // 处理后大小，bytes
        $this->addColumn('{{%task}}', 'output_size', $this->bigInteger->defaultValue(null));

    }

    public function down()
    {
        $this->dropColumn('{{%task}}', 'output_size');
        $this->dropColumn('{{%task}}', 'input_size');
    }
}
