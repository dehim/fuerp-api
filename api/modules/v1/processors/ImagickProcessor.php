<?php

namespace api\modules\v1\processors;

use api\modules\v1\models\Task;
use api\modules\v1\processors\exceptions\ImageProcessException;
use api\modules\v1\processors\options\ImageOptions;
use Imagick;

class ImagickProcessor implements ImageProcessorInterface
{
    /** 安全限制 */
    private const MAX_PIXELS = 60_000_000; // ~ 8000x8000
    private const MAX_MEMORY_MB = 256;

    public function process(Task $task): ImageProcessResult
    {
        if (!class_exists(Imagick::class)) {
            throw new ImageProcessException('Imagick not installed');
        }

        if (!is_file($task->input_path)) {
            throw new ImageProcessException('Input file not found');
        }

        $options = ImageOptions::fromJson($task->options);

        $imagick = new Imagick();

        // 🛡️ 资源限制（必须在 readImage 前）
        $imagick->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, self::MAX_MEMORY_MB);
        $imagick->setResourceLimit(Imagick::RESOURCETYPE_MAP, self::MAX_MEMORY_MB);

        $imagick->readImage($task->input_path);

        $this->guardImageSize($imagick);

        // 🧠 resize
        if (!empty($options['resize'])) {
            $this->applyResize($imagick, $options['resize']);
        }

        // 🎨 quality
        if (!empty($options['quality'])) {
            $imagick->setImageCompressionQuality((int)$options['quality']);
        }

        // 📦 exif
        if (empty($options['keepExif'])) {
            $imagick->stripImage();
        }

        // 🖼 format
        $format = $options['format'] ?? 'original';
        if ($format !== 'original') {
            $imagick->setImageFormat($format);
        }

        $outputPath = $this->buildOutputPath($task, $format);
        $imagick->writeImage($outputPath);
        $imagick->clear();

        return new ImageProcessResult(
            $outputPath,
            filesize($outputPath)
        );
    }

    private function applyResize(Imagick $imagick, $resize): void
    {
        if ($resize->mode === 'proportional') {
            if ($resize->type === 'width') {
                $imagick->resizeImage($resize->value, 0, Imagick::FILTER_LANCZOS, 1);
            } else {
                $imagick->resizeImage(0, $resize->value, Imagick::FILTER_LANCZOS, 1);
            }
        } else {
            $imagick->resizeImage(
                $resize->width,
                $resize->height,
                Imagick::FILTER_LANCZOS,
                1,
                true
            );
        }
    }

    private function guardImageSize(Imagick $imagick): void
    {
        $w = $imagick->getImageWidth();
        $h = $imagick->getImageHeight();

        if ($w * $h > self::MAX_PIXELS) {
            throw new ImageProcessException("Image too large: {$w}x{$h}");
        }
    }

    private function buildOutputPath(Task $task, string $format): string
    {
        $dir = dirname($task->input_path);
        $ext = $format === 'original'
            ? pathinfo($task->input_path, PATHINFO_EXTENSION)
            : $format;

        return $dir . '/out_' . $task->id . '.' . $ext;
    }
}
