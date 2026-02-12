<?php

use api\modules\v1\models\Task;
use api\modules\v1\processors\ImagickProcessor;
use api\modules\v1\processors\ZipPackProcessor;

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

/**
 * ============================================================
 * 🎯 Processor 映射注册（任务类型 → 处理器类）
 * ============================================================
 *
 * Worker 会通过：
 * Yii::$container->get('processor.map')
 * 来获取映射关系
 *
 * 再根据 $task->type 获取对应 Processor
 */

Yii::$container->set('processor.map', [
    Task::TYPE_COMPRESS => ImagickProcessor::class,
    Task::TYPE_PACK => ZipPackProcessor::class,
]);

/**
 * （可选）
 * 如果未来 Processor 有依赖注入需求，
 * 可以单独注册类级别绑定，例如：
 *
 * Yii::$container->set(ImagickProcessor::class, [
 *     'class' => ImagickProcessor::class,
 * ]);
 *
 * 目前无需额外配置。
 */
