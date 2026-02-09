<?php

use yii\db\Migration;

class m250209_000005_add_input_size_output_size_to_task_table extends Migration
{
    public function up()
    {
        if ($this->db->driverName === 'sqlite') {
            // SQLite 不支持 COMMENT ON COLUMN，只能添加注释字段
            $this->addColumn('{{%task}}', 'input_size', $this->bigInteger()->defaultValue(null));
            $this->addColumn('{{%task}}', 'output_size', $this->bigInteger()->defaultValue(null));
        } else {
            $this->addColumn('{{%task}}', 'input_size', $this->bigInteger()->defaultValue(null)->comment('原始文件大小（bytes）'));
            $this->addColumn('{{%task}}', 'output_size', $this->bigInteger()->defaultValue(null)->comment('处理后文件大小（bytes）'));
        }
    }

    public function down()
    {
        $this->dropColumn('{{%task}}', 'output_size');
        $this->dropColumn('{{%task}}', 'input_size');
    }
}
