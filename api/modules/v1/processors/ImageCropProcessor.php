<?php

namespace api\modules\v1\processors;

use api\modules\v1\models\Task;
use Imagick;

class ImageCropProcessor implements ImageProcessorInterface
{
    private const MAX_PIXELS = 60000000;
    private const MAX_MEMORY_MB = 256;

    public function process(Task $task): ImageProcessResult
    {
        if (!class_exists(Imagick::class)) {
            throw new \RuntimeException('Imagick not installed');
        }

        $raw = json_decode($task->options, true);

        if (!is_array($raw)) {
            throw new \RuntimeException('Invalid task options');
        }

        $snapshot = $raw['asset_snapshot'] ?? null;
        $options = $raw['process_options'] ?? null;

        if (!$snapshot || !$options) {
            throw new \RuntimeException('Invalid crop options');
        }

        $inputPath = $snapshot['storage_path'] ?? null;

        if (!$inputPath || !is_file($inputPath)) {
            throw new \RuntimeException('Input file not found');
        }

        $imagick = new Imagick();

        $imagick->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, self::MAX_MEMORY_MB);
        $imagick->setResourceLimit(Imagick::RESOURCETYPE_MAP, self::MAX_MEMORY_MB);

        $imagick->readImage($inputPath);

        $this->guardImageSize($imagick);

        /**
         * 1️⃣ Rotate
         */
        if (!empty($options['rotate'])) {
            $angle = intval($options['rotate']);

            if ($angle !== 0) {
                $imagick->rotateImage('none', $angle);
                $imagick->setImagePage(0, 0, 0, 0); // 🔥 很重要
            }
        }

        /**
         * 2️⃣ Flip
         */
        if (!empty($options['flip'])) {

            if (!empty($options['flip']['horizontal'])) {
                $imagick->flopImage();
            }

            if (!empty($options['flip']['vertical'])) {
                $imagick->flipImage();
            }

            $imagick->setImagePage(0, 0, 0, 0); // 🔥 保证重置虚拟画布
        }

        /**
         * 3️⃣ Crop（必须最后）
         */
        if (!empty($options['crop'])) {
            $crop = $options['crop'];

            $imagick->cropImage(
                intval($crop['width']),
                intval($crop['height']),
                intval($crop['x']),
                intval($crop['y'])
            );

            $imagick->setImagePage(0, 0, 0, 0);
        }

        /**
         * 4️⃣ Format
         */
        $format = $options['output']['format'] ?? 'original';

        if ($format !== 'original') {
            $imagick->setImageFormat($format);
        }

        $finalFormat = strtolower($imagick->getImageFormat());

        /**
         * 5️⃣ 输出路径
         */
        $outputPath = $this->buildOutputPath($task, $inputPath, $format);

        $imagick->writeImage($outputPath);
        $imagick->clear();

        return new ImageProcessResult(
            $outputPath,
            filesize($outputPath)
        );
    }

    private function guardImageSize(Imagick $imagick): void
    {
        $w = $imagick->getImageWidth();
        $h = $imagick->getImageHeight();

        if ($w * $h > self::MAX_PIXELS) {
            throw new \RuntimeException("Image too large: {$w}x{$h}");
        }
    }

    private function buildOutputPath(Task $task, string $inputPath, string $format): string
    {
        $dir = dirname($inputPath);

        $ext = $format === 'original'
          ? pathinfo($inputPath, PATHINFO_EXTENSION)
          : $format;

        return $dir . '/crop_' . $task->id . '.' . $ext;
    }
}
