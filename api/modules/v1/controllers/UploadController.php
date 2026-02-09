<?php

namespace api\modules\v1\controllers;

use api\components\ApiResponse;
use api\modules\v1\controllers\base\ApiController;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UploadedFile;

class UploadController extends ApiController
{
    /**
     * POST /v1/upload/image
     */
    public function actionImage()
    {
        // ★ 1. 协议级 image_id（来自前端）
        $imageId = Yii::$app->request->post('image_id');
        if (empty($imageId)) {
            throw new BadRequestHttpException('Missing image_id');
        }

        $file = UploadedFile::getInstanceByName('file');

        if (!$file) {
            throw new BadRequestHttpException('No file uploaded');
        }

        if (!$this->isImage($file)) {
            throw new BadRequestHttpException('Invalid image file');
        }

        // ★ 2. 生成存储路径
        $subDir = date('Y/m');
        $basePath = Yii::getAlias('@uploads/images/' . $subDir);

        if (!is_dir($basePath)) {
            mkdir($basePath, 0775, true);
        }

        // ★ 3. 后端生成文件名（与 image_id 解耦）
        $filename = $this->generateFilename($file);
        $fullPath = $basePath . '/' . $filename;

        if (!$file->saveAs($fullPath)) {
            throw new BadRequestHttpException('Failed to save file');
        }

        return ApiResponse::success([
            'image_id' => $imageId,
            'input_path' => $fullPath,
            'original_name' => $file->name,
            'size' => $file->size,
        ]);
    }

    protected function isImage(UploadedFile $file): bool
    {
        return in_array($file->type, [
            'image/jpeg',
            'image/png',
            'image/webp',
        ], true);
    }

    protected function generateFilename(UploadedFile $file): string
    {
        $ext = strtolower($file->extension);

        return md5(uniqid('img_', true)) . '.' . $ext;
    }
}
