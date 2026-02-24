<?php

Yii::setAlias('@common', dirname(__DIR__));
Yii::setAlias('@frontend', dirname(dirname(__DIR__)) . '/frontend');
Yii::setAlias('@backend', dirname(dirname(__DIR__)) . '/backend');
Yii::setAlias('@console', dirname(dirname(__DIR__)) . '/console');

Yii::setAlias(
    '@uploads',
    dirname(Yii::getAlias('@common'), 2) . DIRECTORY_SEPARATOR . 'uploads'
);

$uploadPath = Yii::getAlias('@uploads');
if (!is_dir($uploadPath)) {
    mkdir($uploadPath, 0775, true);
}

/**
 * 🎯 Processor 映射注册
 */
Yii::$container->set('processor.map', function () {
    return [
        \api\modules\v1\models\Task::TYPE_IMAGE_COMPRESS => \api\modules\v1\processors\ImagickProcessor::class,
        \api\modules\v1\models\Task::TYPE_BATCH_PACK => \api\modules\v1\processors\ZipPackProcessor::class,
        \api\modules\v1\models\Task::TYPE_IMAGE_CROP => \api\modules\v1\processors\ImageCropProcessor::class,
    ];
});
