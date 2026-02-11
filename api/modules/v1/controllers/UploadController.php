<?php

namespace api\modules\v1\controllers;

use api\components\ApiResponse;
use api\modules\v1\controllers\base\ApiController;
use api\modules\v1\models\Asset;
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
        $file = UploadedFile::getInstanceByName('file');

        if (!$file) {
            throw new BadRequestHttpException('No file uploaded');
        }

        if (!$this->isImage($file)) {
            throw new BadRequestHttpException('Invalid image file');
        }

        // 1️⃣ 生成 asset_id
        $assetId = $this->generateAssetId();

        // 2️⃣ 构建存储路径
        $subDir = date('Y/m');
        $basePath = Yii::getAlias('@uploads/images/' . $subDir);

        if (!is_dir($basePath) && !mkdir($basePath, 0775, true)) {
            throw new BadRequestHttpException('Failed to create directory');
        }

        $filename = $assetId . '.' . strtolower($file->extension);
        $fullPath = $basePath . '/' . $filename;

        if (!$file->saveAs($fullPath)) {
            throw new BadRequestHttpException('Failed to save file');
        }

        // 3️⃣ 计算 hash（用于去重或未来扩展）
        $hash = hash_file('sha256', $fullPath);

        // 4️⃣ 获取图片尺寸
        [$width, $height] = @getimagesize($fullPath) ?: [null, null];

        // 5️⃣ 写入 asset 表
        $asset = new Asset();
        $asset->id = $assetId;
        $asset->type = 'image';
        $asset->storage_disk = 'local';
        $asset->storage_path = $fullPath;
        $asset->storage_hash = $hash;
        $asset->original_name = $file->name;
        $asset->mime_type = $file->type;
        $asset->extension = strtolower($file->extension);
        $asset->size = filesize($fullPath);
        $asset->width = $width;
        $asset->height = $height;
        $asset->is_temporary = 1;
        $asset->expires_at = time() + 3600 * 24; // 默认24小时过期
        $asset->created_at = time();
        $asset->created_ip = Yii::$app->request->userIP;

        if (!$asset->save()) {
            unlink($fullPath);

            throw new BadRequestHttpException('Failed to save asset record');
        }

        // 6️⃣ 返回 asset_id（不再暴露物理路径）
        return ApiResponse::success([
            'asset_id' => $assetId,
            'original_name' => $asset->original_name,
            'size' => $asset->size,
            'width' => $asset->width,
            'height' => $asset->height,
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

    protected function generateAssetId(): string
    {
        return bin2hex(random_bytes(16)); // 32位安全ID
    }
}
