<?php

namespace api\modules\v1\processors;

use api\modules\v1\models\Task;
use api\modules\v1\processors\exceptions\ImageProcessException;
use api\modules\v1\processors\options\ImageOptions;
use Imagick;

class ImagickProcessor implements ImageProcessorInterface
{
    private const MAX_PIXELS = 60000000; // ~8000x8000
    private const MAX_MEMORY_MB = 256;

    public function process(Task $task): ImageProcessResult
    {
        try {
            echo "[DEBUG] process() entered\n";

            if (!class_exists(Imagick::class)) {
                throw new ImageProcessException('Imagick not installed');
            }

            echo "[DEBUG] Imagick class exists\n";

            if (!is_file($task->input_path)) {
                throw new ImageProcessException('Input file not found');
            }

            echo "[DEBUG] input file exists: {$task->input_path}\n";

            echo "[DEBUG] raw options=" . var_export($task->options, true) . "\n";

            $options = ImageOptions::fromJson($task->options);
            echo "[DEBUG] options parsed\n";
            echo "[DEBUG] options=" . json_encode($options, JSON_UNESCAPED_SLASHES) . "\n";

            $imagick = new Imagick();
            echo "[DEBUG] Imagick object created\n";

            $imagick->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, self::MAX_MEMORY_MB);
            $imagick->setResourceLimit(Imagick::RESOURCETYPE_MAP, self::MAX_MEMORY_MB);
            echo "[DEBUG] resource limits set\n";

            $imagick->readImage($task->input_path);
            echo "[DEBUG] image read\n";

            $this->guardImageSize($imagick);
            echo "[DEBUG] image size ok\n";

            if ($options->resize !== null) {
                echo "[DEBUG] applying resize\n";
                $this->applyResize($imagick, $options->resize);
                echo "[DEBUG] resize done\n";
            }

            if ($options->quality !== null) {
                echo "[DEBUG] setting quality={$options->quality}\n";
                $imagick->setImageCompressionQuality($options->quality);
                echo "[DEBUG] quality set\n";
            }

            if ($options->keepExif === false) {
                echo "[DEBUG] stripping exif\n";
                $imagick->stripImage();
                echo "[DEBUG] exif stripped\n";
            }

            $format = $options->format ?? 'original';
            echo "[DEBUG] format={$format}\n";

            if ($format !== 'original') {
                $imagick->setImageFormat($format);
                echo "[DEBUG] format set\n";
            }

            $outputPath = $this->buildOutputPath($task, $format);
            echo "[DEBUG] outputPath={$outputPath}\n";

            $imagick->writeImage($outputPath);
            echo "[DEBUG] image written\n";

            $imagick->clear();
            echo "[DEBUG] imagick cleared\n";

            return new ImageProcessResult($outputPath, filesize($outputPath));
        } catch (\Throwable $e) {
            echo "[FATAL] " . get_class($e) . "\n";
            echo "[FATAL] " . $e->getMessage() . "\n";
            echo "[FATAL] " . $e->getFile() . ":" . $e->getLine() . "\n";
            echo $e->getTraceAsString() . "\n";

            throw $e; // 继续让 task 失败
        }
    }

    private function applyResize(Imagick $imagick, $resize): void
    {
        switch ($resize->mode) {
            case 'proportional':
                if ($resize->type === 'width' || $resize->type === 'long-edge' || $resize->type === 'short-edge') {
                    $w = ($resize->type === 'width') ? $resize->value : 0;
                    $h = ($resize->type === 'height') ? $resize->value : 0;
                    // 长边或短边等逻辑可以根据实际需求扩展
                    $imagick->resizeImage($w, $h, Imagick::FILTER_LANCZOS, 1);
                }
                break;

            case 'custom':
                if ($resize->width !== null && $resize->height !== null) {
                    $imagick->resizeImage($resize->width, $resize->height, Imagick::FILTER_LANCZOS, 1, true);
                }
                break;

            case 'original':
                // 原图模式，不做任何操作
                return;

            default:
                throw new \RuntimeException('Unknown resize mode: ' . $resize->mode);
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
