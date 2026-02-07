<?php

Yii::setAlias('@common', dirname(__DIR__));
Yii::setAlias('@frontend', dirname(dirname(__DIR__)) . '/frontend');
Yii::setAlias('@backend', dirname(dirname(__DIR__)) . '/backend');
Yii::setAlias('@console', dirname(dirname(__DIR__)) . '/console');
// 定义上传文件的别名路径: 选择当前代码根目录的上两级目录下的“uploads”位置
Yii::setAlias('@uploads', dirname(Yii::getAlias('@app'), 2) . DIRECTORY_SEPARATOR . 'uploads');
