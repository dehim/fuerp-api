<?php

Yii::setAlias('@common', dirname(__DIR__));
Yii::setAlias('@frontend', dirname(dirname(__DIR__)) . '/frontend');
Yii::setAlias('@backend', dirname(dirname(__DIR__)) . '/backend');
Yii::setAlias('@console', dirname(dirname(__DIR__)) . '/console');
/**
 * 上传目录：
 * 指向 项目根目录的上两级 /uploads
 * 兼容 Windows / Linux
 */
Yii::setAlias(
    '@uploads',
    dirname(Yii::getAlias('@common'), 2) . DIRECTORY_SEPARATOR . 'uploads'
);
// 在 bootstrap 里顺便确保目录存在
$uploadPath = Yii::getAlias('@uploads');
if (!is_dir($uploadPath)) {
    mkdir($uploadPath, 0775, true);
}
